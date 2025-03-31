<?php

namespace App\GameModels\Factory;

use App\CQRS\Queries\GameModes\BaseGameModeQuery;
use App\CQRS\Queries\GameModes\BaseGameModeSingleQuery;
use App\CQRS\Queries\GameModes\FindModeByNameQuery;
use App\Exceptions\GameModeNotFoundException;
use App\GameModels\DataObjects\BaseGameModeRow;
use App\GameModels\Game\GameModes\AbstractMode;
use App\Models\System;
use App\Models\SystemType;
use Lsr\Helpers\Tools\Timer;
use Lsr\Lg\Results\Enums\GameModeType;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Lsr\Orm\Interfaces\FactoryInterface;
use Nette\Utils\Strings;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Factory for game mode models
 *
 * Works with multiple different laser game systems.
 *
 * @implements FactoryInterface<AbstractMode>
 */
class GameModeFactory implements FactoryInterface
{
    /** @var array<string, class-string<AbstractMode>> */
    private static array $gameModeClasses = [];

    /**
     * @param  int  $id
     * @param  array{system?:string|System|SystemType}  $options
     *
     * @return AbstractMode|null
     * @throws GameModeNotFoundException
     */
    public static function getById(int $id, array $options = []) : ?AbstractMode {
        $query = new BaseGameModeSingleQuery()->id($id);
        if (isset($options['system'])) {
            $query->systems($options['system']);
        }
        $mode = $query->get();

        $system = ($mode->systems ?? $options['system'] ?? null);
        return self::findModeObject($system, $mode, $mode->type);
    }

    /**
     * @param  string|System|string[]|null  $system
     *
     * @throws GameModeNotFoundException
     */
    public static function findModeObject(
      string | null | System | SystemType | int | array $system,
      ?BaseGameModeRow                                  $mode,
      GameModeType     $modeType,
      ?OutputInterface $output = null,
    ) : AbstractMode {
        Timer::startIncrementing('factory.gamemode');
        $output?->writeln(
          'Finding game mode object... '.print_r(['system' => $system, 'mode' => $mode, 'type' => $modeType], true),
          OutputInterface::VERBOSITY_DEBUG,
        );

        // Normalize system value
        if (is_string($system)) {
            $systems = explode(',', $system);
            if (count($systems) > 1) {
                $system = $systems;
            }
        }
        else {
            if ($system === null) {
                $system = [];
                foreach (System::getActive() as $s) {
                    $system[] = $s->type->value;
                }
            }
        }

        if (is_array($system)) {
            foreach ($system as $sys) {
                $sys = self::normalizeSystemToString($sys);
                $class = self::tryFindingModeClass($sys, $mode, $modeType, $output);
                if ($class !== null) {
                    Timer::stop('factory.gamemode');
                    return self::getModeObject($class, $mode);
                }
            }
            // Get generic game mode class
            $class = 'App\\GameModels\\Game\\GameModes\\';
            if (isset($mode)) {
                $class .= (GameModeType::TEAM === $modeType ? 'CustomTeamMode' : 'CustomSoloMode');
            }
            else {
                $class .= (GameModeType::TEAM === $modeType ? 'TeamDeathmatch' : 'Deathmatch');
            }
            $output?->writeln('Using generic class: '.$class, OutputInterface::VERBOSITY_DEBUG);
            // Cache found class
            foreach ($system as $sys) {
                $sys = self::normalizeSystemToString($sys);
                $key = $sys.'_'.$modeType->value;
                if (isset($mode)) {
                    $key .= '_'.$mode->id_mode;
                }
                /** @var class-string<AbstractMode> $class */
                self::$gameModeClasses[$key] = $class;
            }
            Timer::stop('factory.gamemode');
            return self::getModeObject($class, $mode);
        }

        $system = self::normalizeSystemToString($system);
        $class = self::tryFindingModeClass($system, $mode, $modeType, $output);
        if ($class !== null) {
            Timer::stop('factory.gamemode');
            return self::getModeObject($class, $mode);
        }
        // Get generic game mode class
        $class = 'App\\GameModels\\Game\\GameModes\\';
        if (isset($mode)) {
            $class .= (GameModeType::TEAM === $modeType ? 'CustomTeamMode' : 'CustomSoloMode');
        }
        else {
            $class .= (GameModeType::TEAM === $modeType ? 'TeamDeathmatch' : 'Deathmatch');
        }
        // Cache found class
        $key = $system.'_'.$modeType->value;
        if (isset($mode)) {
            $key .= '_'.$mode->id_mode;
        }
        $output?->writeln('Using generic class: '.$class, OutputInterface::VERBOSITY_DEBUG);
        /** @var class-string<AbstractMode> $class */
        self::$gameModeClasses[$key] = $class;
        Timer::stop('factory.gamemode');
        return self::getModeObject($class, $mode);
    }

    /**
     * @param  int|string|System|SystemType|null  $system
     *
     * @return value-of<SystemType>|null
     */
    private static function normalizeSystemToString(int | string | SystemType | System | null $system) : ?string {
        if (is_string($system)) {
            // Validate
            $type = SystemType::tryFrom($system);
            return $type?->value;
        }
        if (is_int($system)) {
            try {
                $system = System::get($system);
                return $system->type->value;
            } catch (ModelNotFoundException) {
                return null;
            }
        }
        if ($system instanceof System) {
            return $system->type->value;
        }
        if ($system instanceof SystemType) {
            return $system->value;
        }
        return $system;
    }

    /**
     * @param  string  $system
     * @param  BaseGameModeRow|null  $mode
     * @param  GameModeType  $modeType
     * @return class-string<AbstractMode>|null
     */
    private static function tryFindingModeClass(
      string           $system,
      ?BaseGameModeRow $mode,
      GameModeType     $modeType,
      ?OutputInterface $output = null,
    ) : ?string {
        $key = $system.'_'.$modeType->value;
        if (isset($mode)) {
            $key .= '_'.$mode->id_mode;
        }

        $output?->writeln('Finding mode class - '.$key, OutputInterface::VERBOSITY_DEBUG);

        if (isset(self::$gameModeClasses[$key])) {
            $output?->writeln('Cache hit', OutputInterface::VERBOSITY_DEBUG);
            return self::$gameModeClasses[$key];
        }

        $classBase = 'App\\GameModels\\Game\\';
        $classSystem = '';
        if (!empty($system)) {
            $classSystem = Strings::firstUpper($system).'\\';
        }
        $classNamespace = 'GameModes\\';
        $className = '';
        if (isset($mode)) {
            $name = $mode->name;
            if (is_numeric($name[0])) {
                $name = 'M'.$name;
            }
            $dbName = str_replace([' ', '.', '_', '-', ','], '', Strings::toAscii(Strings::capitalize($name)));
            $output?->writeln('Transformed mode name: '.$name.' -> '.$dbName, OutputInterface::VERBOSITY_DEBUG);
            $class = $classBase.$classSystem.$classNamespace.$dbName;
            $output?->writeln('Trying class: '.$class, OutputInterface::VERBOSITY_DEBUG);
            if (class_exists($class)) {
                self::$gameModeClasses[$key] = $class;
                return $class;
            }
            $class = $classBase.$classSystem.$classNamespace.strtoupper($dbName);
            $output?->writeln('Trying class: '.$class, OutputInterface::VERBOSITY_DEBUG);
            if (class_exists($class)) {
                self::$gameModeClasses[$key] = $class;
                return $class;
            }
            else {
                $classSystem = '';
                if ($modeType === GameModeType::TEAM) {
                    $className = 'CustomTeamMode';
                }
                else {
                    $className = 'CustomSoloMode';
                }
            }
        }

        if (empty($className)) {
            if ($modeType === GameModeType::TEAM) {
                $className = 'TeamDeathmatch';
            }
            else {
                $className = 'Deathmatch';
            }
        }
        $class = $classBase.$classSystem.$classNamespace.$className;
        $output?->writeln('Trying class: '.$class, OutputInterface::VERBOSITY_DEBUG);
        if (class_exists($class)) {
            self::$gameModeClasses[$key] = $class;
            return $class;
        }
        $output?->writeln('Nothing found', OutputInterface::VERBOSITY_DEBUG);
        return null;
    }

    /**
     * @param  class-string<AbstractMode>  $class
     * @param  BaseGameModeRow|null  $mode
     * @return AbstractMode
     * @throws GameModeNotFoundException
     */
    private static function getModeObject(string $class, ?BaseGameModeRow $mode) : AbstractMode {
        if (!class_exists($class)) {
            throw new GameModeNotFoundException(
              'Cannot find game mode class: '.$class
            );
        }

        $args = [];
        if (isset($mode)) {
            $args[] = $mode->id_mode;
        }
        /** @var AbstractMode $mode */
        $mode = new $class(...$args);
        return $mode;
    }

    /**
     * @param  string  $modeName  Raw game mode name
     * @param  GameModeType  $modeType  Mode type: 0 = Solo, 1 = Team
     * @param  value-of<SystemType>|SystemType|SystemType|null  $system  System name
     *
     * @return AbstractMode
     * @throws GameModeNotFoundException
     */
    public static function find(
      string                              $modeName,
      GameModeType                        $modeType = GameModeType::TEAM,
      null | string | SystemType | System $system = null,
      ?OutputInterface                    $output = null,
    ) : AbstractMode {
        $query = new FindModeByNameQuery()->consoleName($modeName)->type($modeType);
        if ($system !== null) {
            $query->systems($system);
        }
        $mode = $query->get();
        if ($system === null && $mode->systems !== null) {
            $system = $mode->systems;
        }
        return self::findModeObject($system, $mode, $modeType, $output);
    }

    /**
     * @param  string  $modeName  Raw game mode name
     * @param  GameModeType  $modeType  Mode type: 0 = Solo, 1 = Team
     * @param  string  $system  System name
     *
     * @return AbstractMode
     * @throws GameModeNotFoundException
     */
    public static function findByName(
      string                              $modeName,
      GameModeType                        $modeType = GameModeType::TEAM,
      null | string | SystemType | System $system = null
    ) : AbstractMode {
        $query = new FindModeByNameQuery()->name($modeName)->type($modeType);
        if ($system !== null) {
            $query->systems($system);
        }
        $mode = $query->get();
        if ($system === null && $mode->systems !== null) {
            $system = $mode->systems;
        }
        return self::findModeObject($system, $mode, $modeType);
    }

    /**
     * @param  class-string<AbstractMode>|object  $object
     *
     * @return int
     * @throws GameModeNotFoundException
     */
    public static function getIdByObject(string | object $object) : int {
        $modes = self::getAll();
        foreach ($modes as $mode) {
            if ($mode instanceof $object && isset($mode->id)) {
                return $mode->id;
            }
        }
        return 0;
    }

    /**
     * @param  array{system?:value-of<SystemType>|SystemType|System|int,rankable?:bool,all?:bool,public?:bool}  $options
     *
     * @return AbstractMode[]
     * @throws GameModeNotFoundException
     */
    public static function getAll(array $options = []) : array {
        $query = new BaseGameModeQuery();
        $system = null;
        if (isset($options['system'])) {
            $system = $options['system'];
            $query->systems($options['system']);
        }
        if (isset($options['rankable'])) {
            $query->rankable($options['rankable']);
        }
        if (!isset($options['all']) || !((bool) $options['all'])) {
            $query->active();
        }
        if (isset($options['public'])) {
            $query->public($options['public']);
        }
        $modes = [];
        foreach ($query->get() as $mode) {
            $rowSystem = $system;
            if ($rowSystem === null && $mode->systems !== null) {
                $rowSystem = $mode->systems;
            }
            $modes[] = self::findModeObject($rowSystem, $mode, $mode->type);
        }
        return $modes;
    }
}

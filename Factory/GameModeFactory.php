<?php

namespace App\GameModels\Factory;

use App\Exceptions\GameModeNotFoundException;
use App\GameModels\Game\GameModes\AbstractMode;
use App\Models\System;
use Dibi\Row;
use Lsr\Db\DB;
use Lsr\Helpers\Tools\Timer;
use Lsr\Lg\Results\Enums\GameModeType;
use Lsr\Orm\Interfaces\FactoryInterface;
use Nette\Utils\Strings;

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
     * @param  array{system?:string}  $options
     *
     * @return AbstractMode|null
     * @throws GameModeNotFoundException
     */
    public static function getById(int $id, array $options = []) : ?AbstractMode {
        /** @var Row|null $mode */
        $mode = DB::select('game_modes', 'id_mode, name, systems, type')->where('id_mode = %i', $id)->fetch();
        $system = (string) ($options['system'] ?? $mode->system ?? '');
        $modeType = GameModeType::tryFrom((string) ($mode->type ?? 'TEAM')) ?? GameModeType::TEAM;
        return self::findModeObject($system, $mode, $modeType);
    }

    /**
     * @param  string|System|string[]|null  $system
     *
     * @throws GameModeNotFoundException
     * @noinspection PhpUndefinedFieldInspection
     */
    public static function findModeObject(
      string | null | System | array $system,
      ?Row                           $mode,
      GameModeType                   $modeType
    ) : AbstractMode {
        Timer::startIncrementing('factory.gamemode');

        // Normalize system value
        if (is_string($system)) {
            $systems = explode(',', $system);
            if (count($systems) > 1) {
                $system = $systems;
            }
        }
        elseif ($system instanceof System) {
            $system = $system->type->value;
        }
        elseif ($system === null) {
            $system = [];
            foreach (System::getActive() as $s) {
                $system[] = $s->type->value;
            }
        }
        /** @var string[]|string $system */

        if (is_array($system)) {
            foreach ($system as $sys) {
                $class = self::tryFindingModeClass($sys, $mode, $modeType);
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
            // Cache found class
            foreach ($system as $sys) {
                $key = $sys.'_'.$modeType->value;
                if (isset($mode)) {
                    $key .= '_'.$mode->id_mode;
                }
                self::$gameModeClasses[$key] = $class;
            }
            Timer::stop('factory.gamemode');
            return self::getModeObject($class, $mode);
        }

        $class = self::tryFindingModeClass($system, $mode, $modeType);
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
        self::$gameModeClasses[$key] = $class;
        Timer::stop('factory.gamemode');
        return self::getModeObject($class, $mode);
    }

    /**
     * @param  string  $system
     * @param  Row|null  $mode
     * @param  GameModeType  $modeType
     * @return class-string<AbstractMode>|null
     */
    public static function tryFindingModeClass(string $system, ?Row $mode, GameModeType $modeType) : ?string {
        $key = $system.'_'.$modeType->value;
        if (isset($mode)) {
            $key .= '_'.$mode->id_mode;
        }

        if (isset(self::$gameModeClasses[$key])) {
            return self::$gameModeClasses[$key];
        }

        $classBase = 'App\\GameModels\\Game\\';
        $classSystem = '';
        if (!empty($system)) {
            $classSystem = GameFactory::systemToNamespace($system).'\\';
        }
        $classNamespace = 'GameModes\\';
        $className = '';
        if (isset($mode)) {
            if (is_numeric($mode->name[0])) {
                $mode->name = 'M'.$mode->name;
            }
            $dbName = str_replace([' ', '.', '_', '-'], '', Strings::toAscii(Strings::capitalize($mode->name)));

            /** @var class-string<AbstractMode> $class */
            $class = $classBase.$classSystem.$classNamespace.$dbName;
            if (class_exists($class)) {
                self::$gameModeClasses[$key] = $class;
                return $class;
            }

            /** @var class-string<AbstractMode> $class */
            $class = $classBase.$classSystem.$classNamespace.strtoupper($dbName);
            if (class_exists($class)) {
                self::$gameModeClasses[$key] = $class;
                return $class;
            }

            $classSystem = '';
            if ($modeType === GameModeType::TEAM) {
                $className = 'CustomTeamMode';
            }
            else {
                $className = 'CustomSoloMode';
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
        /** @var class-string<AbstractMode> $class */
        $class = $classBase.$classSystem.$classNamespace.$className;
        if (class_exists($class)) {
            self::$gameModeClasses[$key] = $class;
            return $class;
        }
        return null;
    }

    /**
     * @param  class-string<AbstractMode>  $class
     * @param  Row|null  $mode
     * @return AbstractMode
     * @throws GameModeNotFoundException
     */
    private static function getModeObject(string $class, ?Row $mode) : AbstractMode {
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
     * @param  string  $system  System name
     *
     * @return AbstractMode
     * @throws GameModeNotFoundException
     */
    public static function find(
      string       $modeName,
      GameModeType $modeType = GameModeType::TEAM,
      string       $system = ''
    ) : AbstractMode {
        /** @var Row|null $mode */
        $mode = DB::select('vModesNames', 'id_mode, name, systems')
                  ->where(
                    '%s LIKE CONCAT(\'%\', [sysName], \'%\')',
                    $modeName
                  )->fetch();
        if (empty($system) && isset($mode->system)) {
            $system = $mode->system;
        }
        return self::findModeObject($system, $mode, $modeType);
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
      string       $modeName,
      GameModeType $modeType = GameModeType::TEAM,
      string       $system = ''
    ) : AbstractMode {
        /** @var Row|null $mode */
        $mode = DB::select('vModesNames', 'id_mode, name, systems')->where('[name] = %s', $modeName)->fetch();
        if (isset($mode->systems)) {
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
     * @param  array{system?:string|System|int,rankable?:bool,all?:bool,public?:bool}  $options
     *
     * @return AbstractMode[]
     * @throws GameModeNotFoundException
     */
    public static function getAll(array $options = []) : array {
        $ids = DB::select('game_modes', 'id_mode, name, systems, type')
          ->cacheTags(AbstractMode::TABLE.'/query');
        if (isset($options['system'])) {
            $system = $options['system'];
            if ($system instanceof System) {
                $system = $system->type->value;
            }
            elseif (is_numeric($system)) {
                $system = System::get((int) $system)->type->value;
            }

            $ids->where('(systems IS NULL OR systems LIKE %~like~)', $system);
        }
        if (isset($options['rankable'])) {
            $ids->where('rankable = %i', $options['rankable'] ? 1 : 0);
        }
        if (!isset($options['all']) || !((bool) $options['all'])) {
            $ids->where('active = 1');
        }
        if (isset($options['public'])) {
            $ids->where('public = %i', (int) $options['public']);
        }
        $ids = $ids->fetchAssoc('id_mode');
        $modes = [];
        foreach ($ids as $mode) {
            $system = $mode->system ?? '';
            $modeType = GameModeType::tryFrom($mode->type ?? 'TEAM') ?? GameModeType::TEAM;
            $modes[] = self::findModeObject($system, $mode, $modeType);
        }
        return $modes;
    }
}

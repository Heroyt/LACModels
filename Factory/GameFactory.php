<?php

namespace App\GameModels\Factory;

use App\Core\App;
use App\GameModels\Game\Game;
use DateTime;
use DateTimeInterface;
use Dibi\Row;
use InvalidArgumentException;
use Lsr\Caching\Cache;
use Lsr\Core\Config;
use Lsr\Db\DB;
use Lsr\Db\Dibi\Fluent;
use Lsr\Helpers\Tools\Strings;
use Lsr\Helpers\Tools\Timer;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Lsr\Orm\Interfaces\FactoryInterface;
use Throwable;

/**
 * Factory for game models
 *
 * Works with multiple different laser game systems.
 *
 * @implements FactoryInterface<Game>
 */
class GameFactory implements FactoryInterface
{

    public const array SYSTEM_MAP = [
      'evo5'       => 'Lasermaxx\\Evo5',
      'evo6'       => 'Lasermaxx\\Evo6',
      'laserforce' => 'Laserforce',
    ];

    /** @var non-empty-string[] */
    private static array $supportedSystems;

    /**
     * Get game by its unique code
     *
     * @param  string  $code
     *
     * @return Game|null
     * @throws Throwable
     * @phpstan-ignore missingType.generics
     */
    public static function getByCode(string $code) : ?Game {
        $game = null;
        Timer::startIncrementing('factory.game');
        /**
         * @var null|object{
         *   id_game:int,
         *   system:string,
         *   code: string,
         *   start: \Dibi\DateTime|null,
         *   end: \Dibi\DateTime|null,
         *   sync: int
         * } $gameRow
         */
        $gameRow = self::queryGames()->where('[code] = %s', $code)->cacheTags('games/'.$code)->fetch();
        if (isset($gameRow)) {
            $game = self::getById((int) $gameRow->id_game, ['system' => $gameRow->system]);
        }
        Timer::stop('factory.game');
        return $game;
    }

    /**
     * Prepare a SQL query for all games (from all systems)
     *
     * @param  bool  $excludeNotFinished
     * @param  DateTimeInterface|null  $date
     * @param  array<string|int, string>  $fields
     *
     * @return Fluent
     */
    public static function queryGames(
      bool               $excludeNotFinished = false,
      ?DateTimeInterface $date = null,
      array              $fields = []
    ) : Fluent {
        $query = DB::select();
        $queries = [];
        $defaultFields = ['id_game', 'system', 'code', 'start', 'end'];
        foreach (self::getSupportedSystems() as $key => $system) {
            $addFields = '';
            if (!empty($fields)) {
                foreach ($fields as $name => $field) {
                    // Prevent duplicate fields
                    if (in_array($name, $defaultFields, true) || in_array($field, $defaultFields, true)) {
                        continue;
                    }
                    if (is_string($name)) {
                        // Allows setting alias
                        $addFields .= ', [g'.$key.'].['.$name.'] as ['.$field.']';
                    }
                    else {
                        // No alias
                        $addFields .= ', [g'.$key.'].['.$field.']';
                    }
                }
            }
            $q = DB::select(
              ["[{$system}_games]", "[g$key]"],
              "[g$key].[id_game], %s as [system], [g$key].[code], [g$key].[start], [g$key].[end]".$addFields,
              $system
            );
            if ($excludeNotFinished) {
                $q->where("[g$key].[end] IS NOT NULL");
            }
            if (isset($date)) {
                $q->where("DATE([g$key].[start]) = %d", $date);
            }
            $queries[] = (string) $q;
        }
        $query->from('%sql', '(('.implode(') UNION ALL (', $queries).')) [t]');
        return $query->cacheTags('games');
    }

    /**
     * Get a list of all supported systems
     *
     * @return non-empty-string[]
     */
    public static function getSupportedSystems() : array {
        if (!isset(self::$supportedSystems)) {
            /** @var Config $config */
            $config = App::getServiceByType(Config::class);
            /** @var string|null $systems */
            $systems = $config->getConfig('ENV')['SUPPORTED_SYSTEMS'] ?? null;
            if (!isset($systems)) {
                // Default config
                self::$supportedSystems = require ROOT.'config/supportedSystems.php';
                return self::$supportedSystems;
            }
            self::$supportedSystems = array_filter(array_map('trim', explode(';', $systems)));
        }
        return self::$supportedSystems;
    }

    /**
     * Get a game model
     *
     * @param  int  $id
     * @param  array{system?:string}  $options
     *
     * @return Game|null
     * @throws Throwable
     * @phpstan-ignore missingType.generics
     */
    public static function getById(int $id, array $options = []) : ?Game {
        $system = $options['system'] ?? '';
        if (empty($system)) {
            throw new InvalidArgumentException('System name is required.');
        }
        Timer::startIncrementing('factory.game');
        try {

            /**
             * @var class-string<Game> $className
             * @phpstan-ignore missingType.generics
             */
            $className = '\\App\\GameModels\\Game\\'.self::systemToNamespace($system).'\\Game';
            if (!class_exists($className)) {
                throw new InvalidArgumentException('Game model of does not exist: '.$className);
            }
            $game = $className::get($id);
        } catch (ModelNotFoundException) {
            Timer::stop('factory.game');
            return null;
        }
        Timer::stop('factory.game');
        return $game;
    }

    /**
     * @param  string  $system
     * @return non-empty-string
     */
    public static function systemToNamespace(string $system) : string {
        return (self::SYSTEM_MAP[strtolower($system)] ?? Strings::toPascalCase($system));
    }

    /**
     * Get the last played game
     *
     * @param  string  $system  System filter
     * @param  bool  $excludeNotFinished  By default, filter unfinished games
     *
     * @return Game|null
     * @throws Throwable
     * @phpstan-ignore missingType.generics
     */
    public static function getLastGame(string $system = 'all', bool $excludeNotFinished = true) : ?Game {
        if ($system === 'all') {
            $query = self::queryGames(true);
        }
        else {
            $query = self::queryGamesSystem($system, $excludeNotFinished);
        }
        /**
         * @var null|object{
         *   id_game:int,
         *   system:string,
         *   code: string,
         *   start: \Dibi\DateTime|null,
         *   end: \Dibi\DateTime|null,
         *   sync: int
         * } $row
         */
        $row = $query->orderBy('end')->desc()->fetch(cache: false);
        if (isset($row)) {
            /** @noinspection PhpUndefinedFieldInspection */
            return self::getById((int) $row->id_game, ['system' => $row->system]);
        }
        return null;
    }

    /**
     * Prepare a SQL query for all games (from one system)
     *
     * @param  string  $system
     * @param  bool  $excludeNotFinished
     * @param  DateTime|null  $date
     * @param  array<string|int, string>  $fields
     *
     * @return Fluent
     */
    public static function queryGamesSystem(
      string    $system,
      bool      $excludeNotFinished = false,
      ?DateTime $date = null,
      array     $fields = []
    ) : Fluent {
        $defaultFields = ['id_game', 'system', 'code', 'start', 'end', 'sync'];
        $addFields = '';
        if (!empty($fields)) {
            foreach ($fields as $name => $field) {
                // Prevent duplicate fields
                if (in_array($name, $defaultFields, true) || in_array($field, $defaultFields, true)) {
                    continue;
                }
                if (is_string($name)) {
                    // Allows setting alias
                    $addFields .= ', ['.$name.'] as ['.$field.']';
                }
                else {
                    // No alias
                    $addFields .= ', ['.$field.']';
                }
            }
        }
        $query = DB::select(
          ["[{$system}_games]"],
          "[id_game], %s as [system], [code], [start], [end]".$addFields,
          $system
        )
                   ->cacheTags('games', 'games/'.$system);
        if ($excludeNotFinished) {
            $query->where("[end] IS NOT NULL");
        }
        if (isset($date)) {
            $query->where('DATE([start]) = %d', $date);
        }
        return $query;
    }

    /**
     * Get games for the day
     *
     * @param  DateTimeInterface  $date
     * @param  bool  $excludeNotFinished
     *
     * @return Game[]
     * @throws Throwable
     * @phpstan-ignore missingType.generics
     */
    public static function getByDate(DateTimeInterface $date, bool $excludeNotFinished = false) : array {
        Timer::startIncrementing('factory.game');
        /** @var Cache $cache */
        $cache = App::getService('cache');
        /** @var Row[]|null $rows */
        $rows = $cache->load(
          'games/'.$date->format('Y-m-d').($excludeNotFinished ? '/finished' : ''),
          static fn() => self::queryGames($excludeNotFinished)
                             ->cacheTags('games', 'games/'.$date->format('Y-m-d'))
                             ->where('DATE([start]) = %d', $date)
                             ->orderBy('start')->desc()
                             ->fetchAll(),
          [
            'tags'   => [
              'games',
              'models',
              'games/'.$date->format('Y-m-d'),
            ],
            'expire' => '7 days',
          ],
        );
        $games = [];
        foreach ($rows ?? [] as $row) {
            $game = self::getById((int) $row->id_game, ['system' => $row->system]);
            if (isset($game)) {
                $games[] = $game;
            }
        }
        Timer::stop('factory.game');
        return $games;
    }

    /**
     * Get game counts for each dates
     *
     * @param  string  $format
     * @param  bool  $excludeNotFinished
     *
     * @return array<string,int>
     * @noinspection PhpUndefinedFieldInspection
     */
    public static function getGamesCountPerDay(string $format = 'Y-m-d', bool $excludeNotFinished = false) : array {
        $rows = self::queryGameCountPerDay($excludeNotFinished)->fetchAll();
        $return = [];
        foreach ($rows as $row) {
            if (!isset($row->date)) {
                continue;
            }
            /** @var \Dibi\DateTime $date */
            $date = $row->date;
            /** @var int $count */
            $count = $row->count;
            $return[$date->format($format)] = $count;
        }
        return $return;
    }

    /**
     * @param  bool  $excludeNotFinished
     *
     * @return Fluent
     */
    public static function queryGameCountPerDay(bool $excludeNotFinished = false) : Fluent {
        $query = DB::select(null, '[date], count(*) as [count]');
        $queries = [];
        foreach (self::getSupportedSystems() as $key => $system) {
            $q = DB::select(["[{$system}_games]", "[g$key]"], "[g$key].[code], DATE([g$key].[start]) as [date]");
            if ($excludeNotFinished) {
                $q->where("[g$key].[end] IS NOT NULL");
            }
            $queries[] = (string) $q;
        }
        $query
          ->from('%sql', '(('.implode(') UNION ALL (', $queries).')) [t]')
          ->groupBy('date');
        return $query->cacheTags('games', 'games/counts');
    }

    /**
     * Get team colors for all supported systems
     *
     * @return string[][]
     */
    public static function getAllTeamsColors() : array {
        $colors = [];
        foreach (self::getSupportedSystems() as $system) {
            /** @var class-string $className */
            $className = 'App\GameModels\Game\\'.self::systemToNamespace($system).'\Game';
            if (method_exists($className, 'getTeamColors')) {
                $colors[$system] = $className::getTeamColors();
            }
        }
        return $colors;
    }

    /**
     * Get team names for all supported systems
     *
     * @return string[][]
     */
    public static function getAllTeamsNames() : array {
        $colors = [];
        foreach (self::getSupportedSystems() as $system) {
            /** @var class-string $className */
            $className = 'App\GameModels\Game\\'.self::systemToNamespace($system).'\Game';
            if (method_exists($className, 'getTeamNames')) {
                $colors[$system] = $className::getTeamNames();
            }
        }
        return $colors;
    }

    /**
     * Get available filters for game query based on selected system
     *
     * @param  string|null  $system
     *
     * @return array|string[]
     */
    public static function getAvailableFilters(?string $system = null) : array {
        $fields = [
          'id_game',
          'id_arena',
          'system',
          'code',
          'start',
          'end',
        ];
        if (empty($system)) {
            return $fields;
        }
        if (!in_array($system, self::getSupportedSystems(), true)) {
            throw new InvalidArgumentException('Unsupported or unknown system: '.$system);
        }
        $className = 'App\GameModels\Game\\'.self::systemToNamespace($system).'\Game';
        if (!class_exists($className)) {
            throw new InvalidArgumentException('Cannot find Game class for system: '.$system);
        }

        foreach (get_class_vars($className) as $field => $definition) {
            $fields[] = $field;
        }

        return array_unique($fields);
    }

    /**
     * @param  array{system?:string, excludeNotFinished?: bool}  $options
     *
     * @return Game[]
     * @throws Throwable
     * @phpstan-ignore missingType.generics
     */
    public static function getAll(array $options = []) : array {
        if (!empty($options['system'])) {
            $rows = self::queryGamesSystem(
              $options['system'],
              isset($options['excludeNotFinished']) && $options['excludeNotFinished']
            )->fetchAll();
        }
        else {
            $rows = self::queryGames(isset($options['excludeNotFinished']) && $options['excludeNotFinished'])->fetchAll(
            );
        }
        $models = [];
        foreach ($rows as $row) {
            /** @noinspection PhpUndefinedFieldInspection */
            $game = self::getById((int) $row->id_game, ['system' => $row->system]);
            if (isset($game)) {
                $models[] = $game;
            }
        }
        return $models;
    }
}

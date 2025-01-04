<?php

namespace App\GameModels\Factory;

use App\GameModels\Game\Player;
use InvalidArgumentException;
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
 * @implements FactoryInterface<Player>
 */
class PlayerFactory implements FactoryInterface
{
    /**
     * @param  array{system?:string}  $options
     *
     * @return Player[]
     * @throws Throwable
     */
    public static function getAll(array $options = []) : array {
        if (!empty($options['system'])) {
            $rows = self::queryPlayersSystem($options['system'])->fetchAll();
        }
        else {
            $rows = self::queryPlayers()->fetchAll();
        }
        $models = [];
        foreach ($rows as $row) {
            $model = self::getById($row->id_team, ['system' => $row->system]);
            if (isset($model)) {
                $models[] = $model;
            }
        }
        return $models;
    }

    /**
     * Prepare a SQL query for all players
     *
     * @param  string  $system
     * @param  int[][]  $gameIds
     *
     * @return Fluent
     */
    public static function queryPlayersSystem(string $system, array $gameIds = []) : Fluent {
        $q = DB::select(
          ["[{$system}_players]", "[g]"],
          "[g].[id_player], [g].[id_game], [g].[id_team], %s as [system], [g].[name], [g].[score], [g].[accuracy], [g].[hits], [g].[deaths], [g].[shots]",
          $system
        )
               ->cacheTags('players', 'players/'.$system);
        if (!empty($gameIds)) {
            $q->where("[g].[id_game] IN %in", $gameIds);
        }
        return $q;
    }

    /**
     * Prepare a SQL query for all players (from all systems)
     *
     * @param  int[][]  $gameIds
     *
     * @return Fluent
     */
    public static function queryPlayers(array $gameIds = [], array $fields = []) : Fluent {
        $query = DB::select();
        $queries = self::getPlayersUnionQueries($gameIds, $fields);
        $query->from('%sql', '(('.implode(') UNION ALL (', $queries).')) [t]');
        return $query->cacheTags('players');
    }

    /**
     * @param  int[][]  $gameIds
     *
     * @return string[]
     */
    public static function getPlayersUnionQueries(array $gameIds = [], array $fields = []) : array {
        $queries = [];
        $defaultFields = [
          'id_player',
          'system',
          'id_user',
          'id_game',
          'name',
          'score',
          'skill',
          'accuracy',
          'hits',
          'deaths',
          'shots',
        ];
        foreach (GameFactory::getSupportedSystems() as $key => $system) {
            $addFields = '';
            if (!empty($fields)) {
                foreach ($fields as $name => $field) {
                    // Prevent duplicate fields
                    if (in_array($name, $defaultFields, true) || in_array($field, $defaultFields, true)) {
                        continue;
                    }
                    if (is_string($name)) {
                        // Allows setting alias
                        $addFields .= ', [p'.$key.'].['.$name.'] as ['.$field.']';
                    }
                    else {
                        // No alias
                        $addFields .= ', [p'.$key.'].['.$field.']';
                    }
                }
            }
            $q = DB::select(
              ["[{$system}_players]", "[p$key]"],
              "[p$key].[id_player], [p$key].[id_user], [p$key].[id_game], [p$key].[id_team], %s as [system], [p$key].[name], [p$key].[score], [p$key].[accuracy], [p$key].[hits], [p$key].[deaths], [p$key].[shots], [p$key].[skill]".$addFields,
              $system
            );
            if (!empty($gameIds[$system])) {
                $q->where("[p$key].[id_game] IN %in", $gameIds[$system]);
            }
            $queries[] = (string) $q;
        }
        return $queries;
    }

    /**
     * Get a game model
     *
     * @param  int  $id
     * @param  array{system?:string}  $options
     *
     * @return Player|null
     * @throws Throwable
     */
    public static function getById(int $id, array $options = []) : ?Player {
        $system = $options['system'] ?? '';
        if (empty($system)) {
            throw new InvalidArgumentException('System name is required.');
        }
        Timer::startIncrementing('factory.player');
        try {
            $className = '\\App\\GameModels\\Game\\'.Strings::toPascalCase($system).'\\Player';
            if (!class_exists($className)) {
                throw new InvalidArgumentException('Player model of does not exist: '.$className);
            }
            $player = $className::get($id);
        } catch (ModelNotFoundException $e) {
            Timer::stop('factory.player');
            bdump($e);
            return null;
        }
        Timer::stop('factory.player');
        return $player;
    }

    public static function queryPlayersWithGames(
      array $gameFields = [],
      array $playerFields = [],
      array $modeFields = []
    ) : Fluent {
        $query = DB::select();
        $queries = self::getPlayersWithGamesUnionQueries($gameFields, $playerFields, $modeFields);
        $query->from('%sql', '(('.implode(') UNION ALL (', $queries).')) [t]');
        return $query->cacheTags('players');
    }

    /**
     * @param  array<int|string, string|array{first:string,second:string,operation:string}>  $gameFields
     * @param  array<int|string, string|array{first:string,second:string,operation:string}>  $playerFields
     * @param  array<int|string, string|array{first:string,second:string,operation:string}>  $modeFields
     *
     * @return string[]
     */
    public static function getPlayersWithGamesUnionQueries(
      array $gameFields = [],
      array $playerFields = [],
      array $modeFields = []
    ) : array {
        $defaultPlayerFields = [
          'id_player',
          'id_user',
          'id_team',
          'system',
          'name',
          'score',
          'accuracy',
          'skill',
          'position',
        ];
        $defaultGameFields = ['id_game', 'system', 'code', 'start', 'end'];
        $defaultModeFields = ['id_mode', 'name'];
        $queries = [];
        foreach (GameFactory::getSupportedSystems() as $key => $system) {
            $addFields = '';
            if (!empty($playerFields)) {
                foreach ($playerFields as $name => $field) {
                    // Prevent duplicate fields
                    if (in_array($name, $defaultPlayerFields, true) || in_array($field, $defaultPlayerFields, true)) {
                        continue;
                    }
                    if (is_array($field)) {
                        if (is_string($name)) {
                            // Allows setting alias
                            $addFields .= ', [p'.$key.'].['.$field['first'].']'.$field['operation'].'[p'.$key.'].['.$field['second'].'] as ['.$name.']';
                        }
                        else {
                            // No alias
                            $addFields .= ', [p'.$key.'].['.$field['first'].']'.$field['operation'].'[p'.$key.'].['.$field['second'].']';
                        }
                    }
                    else {
                        if (is_string($name)) {
                            // Allows setting alias
                            $addFields .= ', [p'.$key.'].['.$name.'] as ['.$field.']';
                        }
                        else {
                            // No alias
                            $addFields .= ', [p'.$key.'].['.$field.']';
                        }
                    }
                }
            }
            if (!empty($gameFields)) {
                foreach ($gameFields as $name => $field) {
                    // Prevent duplicate fields
                    if (in_array($name, $defaultGameFields, true) || in_array($field, $defaultGameFields, true)) {
                        continue;
                    }
                    if (is_array($field)) {
                        if (is_string($name)) {
                            // Allows setting alias
                            $addFields .= ', [g'.$key.'].['.$field['first'].']'.$field['operation'].'[g'.$key.'].['.$field['second'].'] as ['.$name.']';
                        }
                        else {
                            // No alias
                            $addFields .= ', [g'.$key.'].['.$field['first'].']'.$field['operation'].'[g'.$key.'].['.$field['second'].']';
                        }
                    }
                    else {
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
            }
            if (!empty($modeFields)) {
                foreach ($modeFields as $name => $field) {
                    // Prevent duplicate fields
                    if (in_array($name, $defaultModeFields, true) || in_array($field, $defaultModeFields, true)) {
                        continue;
                    }
                    if (is_array($field)) {
                        if (is_string($name)) {
                            // Allows setting alias
                            $addFields .= ', [m'.$key.'].['.$field['first'].']'.$field['operation'].'[m'.$key.'].['.$field['second'].'] as ['.$name.']';
                        }
                        else {
                            // No alias
                            $addFields .= ', [m'.$key.'].['.$field['first'].']'.$field['operation'].'[m'.$key.'].['.$field['second'].']';
                        }
                    }
                    else {
                        if (is_string($name)) {
                            // Allows setting alias
                            $addFields .= ', [m'.$key.'].['.$name.'] as ['.$field.']';
                        }
                        else {
                            // No alias
                            $addFields .= ', [m'.$key.'].['.$field.']';
                        }
                    }
                }
            }
            $q = DB::select(
              ["[{$system}_players]", "[p$key]"],
              "[p$key].[id_player], [p$key].[id_user], [p$key].[id_team], %s as [system], [p$key].[name], [p$key].[score], [p$key].[accuracy], [p$key].[skill], [p$key].[position], ".
              "[g$key].[id_game], [g$key].[code], [g$key].[start], [g$key].[end], ".
              "[m$key].[id_mode], [m$key].[name] as [modeName]".
              $addFields,
              $system
            )
                   ->join("[{$system}_games]", "[g$key]")->on("[p$key].[id_game] = [g$key].[id_game]")
                   ->leftJoin("[game_modes]", "[m$key]")->on("[g$key].[id_mode] = [m$key].[id_mode]");
            $queries[] = (string) $q;
        }
        return $queries;
    }
}

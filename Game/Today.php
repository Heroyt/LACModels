<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game;

use Lsr\Db\DB;
use Lsr\Db\Dibi\Fluent;
use Lsr\Helpers\Tools\Strings;

/**
 * Helper class for querying the best players for a day
 */
class Today
{
    public int $games;
    public int $teams;
    public int $players;

    private Fluent $gameQuery;

    /**
     * @template G of Game
     * @template P of Player
     * @template T of Team
     * @param  G  $gameClass
     * @param  P  $playerClass
     * @param  T  $teamClass
     */
    public function __construct(Game $gameClass, Player $playerClass, Team $teamClass) {
        $this->games = DB::select($gameClass::TABLE, 'count(*)')
                         ->where('DATE(start) = %d', $gameClass->start)
                         ->fetchSingle();
        $this->players = DB::select($playerClass::TABLE, 'count(*)')->where(
          'id_game IN %sql',
          $this->todayGames($gameClass)
        )->fetchSingle();
        $this->teams = DB::select($teamClass::TABLE, 'count(*)')->where(
          'id_game IN %sql',
          $this->todayGames($gameClass)
        )->fetchSingle();
    }

    /**
     * @template G of Game
     * @param  G  $gameClass
     *
     * @return Fluent
     */
    private function todayGames(Game $gameClass) : Fluent {
        $this->gameQuery = DB::select($gameClass::TABLE, 'id_game')->where('DATE(start) = %d', $gameClass->start);
        return $this->gameQuery;
    }

    /**
     * @template P of Player
     * @param  P  $player
     * @param  string  $property
     *
     * @return string Returns either one number (a position) or a range of position (ex. 1-3)
     */
    public function getPlayerOrder(Player $player, string $property) : string {
        $better = DB::select($player::TABLE, 'count(*)')
          ->where(
            '[id_game] IN %sql AND %n > %i',
            $this->gameQuery,
            Strings::toSnakeCase($property),
            $player->$property
          )
          ->fetchSingle();
        $same = DB::select($player::TABLE, 'count(*)')
          ->where(
            '[id_game] IN %sql AND %n = %i',
            $this->gameQuery,
            Strings::toSnakeCase($property),
            $player->$property
          )
          ->fetchSingle();
        $better++;
        if ($same === 1) {
            return (string) $better;
        }
        return $better.'-'.($better + $same - 1);
    }
}

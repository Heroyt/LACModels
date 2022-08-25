<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */
namespace App\GameModels\Game;

use Dibi\Fluent;
use Lsr\Core\DB;
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

	public function __construct(Game $gameClass, Player $playerClass, Team $teamClass) {
		/* @phpstan-ignore-next-line */
		$this->games = DB::select($gameClass::TABLE, 'count(*)')->where('DATE(start) = %d', $gameClass->start)->fetchSingle();
		/* @phpstan-ignore-next-line */
		$this->players = DB::select($playerClass::TABLE, 'count(*)')->where('id_game IN %sql', $this->todayGames($gameClass))->fetchSingle();
		/* @phpstan-ignore-next-line */
		$this->teams = DB::select($teamClass::TABLE, 'count(*)')->where('id_game IN %sql', $this->todayGames($gameClass))->fetchSingle();
	}

	private function todayGames(Game $gameClass) : Fluent {
		$this->gameQuery = DB::select($gameClass::TABLE, 'id_game')->where('DATE(start) = %d', $gameClass->start);
		return $this->gameQuery;
	}

	/**
	 * @param Player $player
	 * @param string $property
	 *
	 * @return string Returns either one number (a position) or a range of position (ex. 1-3)
	 */
	public function getPlayerOrder(Player $player, string $property) : string {
		$better = DB::select($player::TABLE, 'count(*)')
								->where('[id_game] IN %sql AND %n > %i', $this->gameQuery, Strings::toSnakeCase($property), $player->$property)
								->fetchSingle();
		$same = DB::select($player::TABLE, 'count(*)')
							->where('[id_game] IN %sql AND %n = %i', $this->gameQuery, Strings::toSnakeCase($property), $player->$property)
							->fetchSingle();
		$better++;
		if ($same === 1) {
			return (string) $better;
		}
		return $better.'-'.($better + $same - 1);
	}

}
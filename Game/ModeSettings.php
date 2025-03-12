<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game;

use Dibi\Row;
use Lsr\Helpers\Tools\Strings;
use OpenApi\Attributes as OA;
use Lsr\Orm\Interfaces\InsertExtendInterface;

/**
 * Data-model for all game mode settings
 *
 * @phpstan-consistent-constructor
 */
#[OA\Schema]
class ModeSettings implements InsertExtendInterface
{
    public function __construct(
      #[OA\Property]
		public bool $public = true,
		#[OA\Property]
		public bool $mines = true,
		#[OA\Property]
		public bool $partWin = true,
		#[OA\Property]
		public bool $partTeams = true,
		#[OA\Property]
		public bool $partPlayers = true,
		#[OA\Property]
		public bool $partHits = true,
		#[OA\Property]
		public bool $partBest = true,
		#[OA\Property]
		public bool $partBestDay = true,
		#[OA\Property]
		public bool $playerScore = true,
		#[OA\Property]
		public bool $playerShots = true,
		#[OA\Property]
		public bool $playerMiss = true,
		#[OA\Property]
		public bool $playerAccuracy = true,
		#[OA\Property]
		public bool $playerMines = true,
		#[OA\Property]
		public bool $playerPlayers = true,
		#[OA\Property]
		public bool $playerPlayersTeams = true,
		#[OA\Property]
		public bool $playerKd = true,
		#[OA\Property]
		public bool $playerFavourites = true,
		#[OA\Property]
		public bool $playerLives = true,
		#[OA\Property]
		public bool $teamScore = true,
		#[OA\Property]
		public bool $teamAccuracy = true,
		#[OA\Property]
		public bool $teamShots = true,
		#[OA\Property]
		public bool $teamHits = true,
		#[OA\Property]
		public bool $teamZakladny = true,
		#[OA\Property]
		public bool $bestScore = true,
		#[OA\Property]
		public bool $bestHits = true,
		#[OA\Property]
		public bool $bestDeaths = true,
		#[OA\Property]
		public bool $bestAccuracy = true,
		#[OA\Property]
		public bool $bestHitsOwn = true,
		#[OA\Property]
		public bool $bestDeathsOwn = true,
		#[OA\Property]
		public bool $bestShots = true,
		#[OA\Property]
		public bool $bestMiss = true,
		#[OA\Property]
      public bool $bestMines = true,
    ) {}

    /**
     * Parse data from DB into the object
     *
     * @param  Row  $row  Row from DB
     *
     * @return static
     */
    public static function parseRow(Row $row) : static {
        $class = new static();
        foreach (get_object_vars($class) as $name => $val) {
            $column = Strings::toSnakeCase($name);
            if (isset($row->$column)) {
                $class->$name = (int) $row->$column === 1;
            }
        }
        return $class;
    }

    /**
     * Add data from the object into the data array for DB INSERT/UPDATE
     *
     * @param  array<string, mixed>  $data
     */
    public function addQueryData(array &$data) : void {
        foreach (get_object_vars($this) as $name => $val) {
            $column = Strings::toSnakeCase($name);
            $data[$column] = $this->$name ? 1 : 0;
        }
    }
}

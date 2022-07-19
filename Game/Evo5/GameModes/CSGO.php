<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game\Evo5\GameModes;

use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\Evo5\Game as Evo5Game;
use App\GameModels\Game\Evo5\Team as Evo5Team;
use App\GameModels\Game\Game;
use App\GameModels\Game\GameModes\AbstractMode;
use App\GameModels\Game\GameModes\CustomResultsMode;
use App\GameModels\Game\Team;
use Lsr\Core\Controller;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\Factory;
use Lsr\Core\Models\Attributes\PrimaryKey;

/**
 * Special LaserMaxx Evo5 game mode
 */
#[PrimaryKey('id_mode')]
#[Factory(GameModeFactory::class)]
class CSGO extends AbstractMode implements CustomResultsMode
{

	public string $name = 'CSGO';

	/**
	 * @param Evo5Game $game
	 *
	 * @return Evo5Team|null
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public function getWin(Game $game) : ?Team {
		$teams = $game->getTeams();
		// Two teams - get the last team alive or team with most hits
		if (count($teams) === 2) {
			/** @var Evo5Team $team1 */
			$team1 = $teams->first();
			$remaining1 = $this->getRemainingLives($team1);
			/** @var Evo5Team $team2 */
			$team2 = $teams->last();
			$remaining2 = $this->getRemainingLives($team2);
			if ($remaining1 === 0 && $remaining2 > 0) {
				return $team2;
			}
			if ($remaining1 > 0 && $remaining2 === 0) {
				return $team1;
			}
			if ($remaining1 > 0 && $remaining2 > 0) {
				$hits1 = $team1->getHits();
				$hits2 = $team2->getHits();
				if ($hits1 > $hits2) {
					return $team1;
				}
				if ($hits2 > $hits1) {
					return $team2;
				}
			}
			return null;
		}

		// More teams - Get alive team with the most hits
		$max = 0;
		$maxTeam = null;
		/** @var Evo5Team $team */
		foreach ($teams as $team) {
			if ($this->getRemainingLives($team) === 0) {
				continue;
			}
			$hits = $team->getHits();
			if ($hits > $max) {
				$max = $hits;
				$maxTeam = $team;
			}
		}
		return $maxTeam;
	}

	/**
	 * @param Evo5Team $team
	 *
	 * @return int
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public function getRemainingLives(Evo5Team $team) : int {
		return $this->getTotalLives($team) - $team->getDeaths();
	}

	/**
	 * @param Evo5Team $team
	 *
	 * @return int
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public function getTotalLives(Evo5Team $team) : int {
		/** @var Evo5Game $game */
		$game = $team->getGame();
		return count($team->getPlayers()) * $game->lives;
	}

	/**
	 * @inheritDoc
	 */
	public function getCustomResultsTemplate(Controller $controller) : string {
		return '';
	}

	/**
	 * @inheritDoc
	 */
	public function getCustomGateTemplate(Controller $controller) : string {
		return 'pages/gate/modes/csgo';
	}
}
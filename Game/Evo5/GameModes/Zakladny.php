<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game\Evo5\GameModes;

use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\Evo5\Player;
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
class Zakladny extends AbstractMode implements CustomResultsMode
{

	public string $name = 'Základny';

	/**
	 * @param Game $game
	 *
	 * @return \App\GameModels\Game\Evo5\Team|null
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public function getWin(Game $game) : ?Team {
		/** @var \App\GameModels\Game\Evo5\Team $team1 */
		$team1 = $game->getTeams()->first();
		$zakladny1 = $this->getBasesDestroyed($team1);
		/** @var \App\GameModels\Game\Evo5\Team $team2 */
		$team2 = $game->getTeams()->last();
		$zakladny2 = $this->getBasesDestroyed($team2);
		if ($zakladny1 > $zakladny2) {
			return $team2;
		}
		if ($zakladny1 < $zakladny2) {
			return $team1;
		}
		return null;
	}

	/**
	 * Get number of bases destroyed
	 *
	 * @param \App\GameModels\Game\Evo5\Team $team
	 *
	 * @return int
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public function getBasesDestroyed(\App\GameModels\Game\Evo5\Team $team) : int {
		$max = max(
			array_map(static function(\App\GameModels\Game\Player $player) : int {
				/** @var Player $player */
				return $player->bonus->shield;
			}, $team->getPlayers()->getAll())
		);
		if ($max === false) {
			return 0;
		}
		return $max;
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
		$controller->params['mode'] = $this;
		return 'pages/gate/modes/zakladny';
	}
}
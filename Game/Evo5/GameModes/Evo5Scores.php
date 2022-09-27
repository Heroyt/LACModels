<?php

namespace App\GameModels\Game\Evo5\GameModes;

use App\GameModels\Game\Evo5\Player;
use App\GameModels\Game\Game;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Logging\Exceptions\DirectoryCreationException;

/**
 * Trait that contains modified recalculate scores methods for the Evo5 system
 */
trait Evo5Scores
{

	protected function recalculateScoresPlayers(Game $game) : void {
		if (!isset($game->scoring)) {
			return;
		}
		try {
			/** @var Player $player */
			foreach ($game->getPlayers() as $player) {
				// Sum powers score
				$player->scorePowers =
					($player->bonus->agent * $game->scoring->agent) +
					($player->bonus->shield * $game->scoring->shield) +
					($player->bonus->invisibility * $game->scoring->invisibility) +
					($player->bonus->machineGun * $game->scoring->machineGun);

				// Sum mine deaths score
				$player->scoreMines = $player->minesHits * $game->scoring->hitPod;

				// Reset score
				$player->score = 0;

				// Add score for hits
				if ($this->isSolo()) {
					$player->score +=
						($player->hits * $game->scoring->hitOther) +
						($player->deaths * $game->scoring->deathOther);
				}
				else {
					$player->score +=
						($player->hitsOther * $game->scoring->hitOther) +
						($player->hitsOwn * $game->scoring->hitOwn) +
						($player->deathsOther * $game->scoring->deathOther) +
						($player->deathsOwn * $game->scoring->deathOwn);
				}

				// Add score for other stuff
				$player->score +=
					($player->shots * $game->scoring->shot) +
					$player->scoreMines +
					$player->scorePowers;
			}
		} catch (ModelNotFoundException|ValidationException|DirectoryCreationException $e) {
		}
	}

}
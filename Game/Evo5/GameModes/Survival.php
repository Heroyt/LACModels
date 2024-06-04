<?php

namespace App\GameModels\Game\Evo5\GameModes;

use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\Game;
use App\GameModels\Game\GameModes\CustomResultsMode;
use App\GameModels\Game\GameModes\ModifyScoresMode;
use App\GameModels\Game\Lasermaxx\GameModes\LaserMaxxScores;
use App\GameModels\Game\Lasermaxx\Player;
use App\Gate\Screens\Results\LaserMaxxSurvivalResultsScreen;
use Lsr\Core\Models\Attributes\Factory;
use Lsr\Core\Models\Attributes\PrimaryKey;

/**
 * Special LaserMaxx Evo5 game mode
 */
#[PrimaryKey('id_mode')]
#[Factory(GameModeFactory::class)] // @phpstan-ignore-line
class Survival extends \App\GameModels\Game\GameModes\Deathmach implements CustomResultsMode, ModifyScoresMode
{

	use LaserMaxxScores;


	public string $name = 'Survival';

	/**
	 * Get a template file containing custom results
	 *
	 * @return string Path to template file
	 */
    public function getCustomResultsTemplate() : string {
        return 'survival';
	}

	/**
	 * Get a template file containing the custom gate results
	 *
	 * @return string Path to template file
	 */
	public function getCustomGateScreen(): string {
      return LaserMaxxSurvivalResultsScreen::class;
	}

    /**
     * @param  \App\GameModels\Game\Lasermaxx\Game  $game
     * @return void
     */
    public function modifyResults(Game $game) : void {
        // Add 1000 score to surviving players
        /** @var Player $player */
        foreach ($game->getPlayers() as $player) {
            if (($player->ammoRest ?? 1) > 0 && $player->getRemainingLives() > 0) {
                $player->scoreBonus += 1000;
                $player->score += 1000;
            }
            $player->ammoRest = $game->ammo - $player->shots;
        }
    }
}
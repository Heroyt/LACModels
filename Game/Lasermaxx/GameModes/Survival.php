<?php

namespace App\GameModels\Game\Lasermaxx\GameModes;

use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\GameModes\CustomResultsMode;
use App\GameModels\Game\GameModes\Deathmatch;
use App\GameModels\Game\Lasermaxx\Game;
use App\Gate\Screens\Results\LaserMaxxSurvivalResultsScreen;
use Lsr\Lg\Results\Interface\Models\GameInterface;
use Lsr\Lg\Results\Interface\Models\ModifyScoresMode;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\PrimaryKey;

/**
 * Special LaserMaxx game mode
 */
#[PrimaryKey('id_mode')]
#[Factory(GameModeFactory::class)] // @phpstan-ignore-line
class Survival extends Deathmatch implements CustomResultsMode, ModifyScoresMode
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
    public function getCustomGateScreen() : string {
        return LaserMaxxSurvivalResultsScreen::class;
    }

    /**
     * @template G of Game
     * @param  G  $game
     * @return void
     */
    public function modifyResults(GameInterface $game) : void {
        // Add 1000 score to surviving players
        foreach ($game->players as $player) {
            if ($player->ammoRest > 0 && $player->getRemainingLives() > 0) {
                $player->scoreBonus += 1000;
                $player->score += 1000;
            }
            $player->ammoRest = max($game->ammo - $player->shots, 0);
        }
    }
}

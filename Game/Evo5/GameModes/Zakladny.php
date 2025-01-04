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
use App\GameModels\Game\Lasermaxx\GameModes\LaserMaxxScores;
use App\GameModels\Game\Lasermaxx\Team as LasermaxxTeam;
use App\GameModels\Game\Team;
use App\Gate\Screens\Results\LaserMaxxZakladnyResultsScreen;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Exceptions\ModelNotFoundException;

/**
 * Special LaserMaxx Evo5 game mode
 */
#[PrimaryKey('id_mode')]
#[Factory(GameModeFactory::class)] // @phpstan-ignore-line
class Zakladny extends AbstractMode implements CustomResultsMode
{
    use LaserMaxxScores;


    public string $name = 'Základny';

    /**
     * @param  Game  $game
     *
     * @return \App\GameModels\Game\Evo5\Team|null
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function getWin(Game $game) : ?Team {
        /** @var \App\GameModels\Game\Evo5\Team $team1 */
        $team1 = $game->teams->first();
        $zakladny1 = $this->getBasesDestroyed($team1);
        /** @var \App\GameModels\Game\Evo5\Team $team2 */
        $team2 = $game->teams->last();
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
     * @param  \App\GameModels\Game\Evo5\Team  $team
     *
     * @return int
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function getBasesDestroyed(\App\GameModels\Game\Evo5\Team $team) : int {
        $max = max(
          array_map(
            static function (\App\GameModels\Game\Player $player) : int {
                /** @var Player $player */
                return $player->bonus->shield;
            },
            $team->players->getAll()
          )
        );
        if ($max === false) {
            return 0;
        }
        return $max;
    }

    /**
     * @inheritDoc
     */
    public function getCustomResultsTemplate() : string {
        return 'zakladny';
    }

    /**
     * @inheritDoc
     */
    public function getCustomGateScreen() : string {
        return LaserMaxxZakladnyResultsScreen::class;
    }

    public function getBaseNameForTeam(LasermaxxTeam $team) : string {
        // TODO: rewrite this to be more modular

        // The only variants are MZ and ZM
        $topTeam = str_ends_with($team->game->modeName, 'ZM') ? 1 : 2; // 1 = green, 2 = blue

        if ($team->color === $topTeam) {
            return lang('Horní základna', context: 'mode.zakladny', domain: 'results');
        }
        return lang('Dolní základna', context: 'mode.zakladny', domain: 'results');
    }
}

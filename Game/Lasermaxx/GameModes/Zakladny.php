<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game\Lasermaxx\GameModes;

use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\GameModes\AbstractMode;
use App\GameModels\Game\GameModes\CustomResultsMode;
use App\GameModels\Game\Lasermaxx\Game;
use App\GameModels\Game\Lasermaxx\Player;
use App\GameModels\Game\Lasermaxx\Team;
use App\Gate\Screens\Results\LaserMaxxZakladnyResultsScreen;
use Lsr\Lg\Results\Interface\Models\GameInterface;
use Lsr\Lg\Results\Interface\Models\TeamGameModeInterface;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\PrimaryKey;

/**
 * Special LaserMaxx game mode
 */
#[PrimaryKey('id_mode')]
#[Factory(GameModeFactory::class)] // @phpstan-ignore-line
class Zakladny extends AbstractMode implements CustomResultsMode, TeamGameModeInterface
{
    use LaserMaxxScores;


    public string $name = 'Základny';

    /**
     * @template T of Team
     * @template P of Player
     * @template G of Game<T, P>
     * @param  G  $game
     * @return T|null
     */
    public function getWin(GameInterface $game) : ?Team {
        /** @var T $team1 */
        $team1 = $game->teams->first();
        $zakladny1 = $this->getBasesDestroyed($team1);
        /** @var T $team2 */
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
     * @template T of Team
     *
     * @param  T  $team
     *
     * @return int
     */
    public function getBasesDestroyed(Team $team) : int {

        $shields = $team->players->map(
        /** @phpstan-ignore argument.type */
          fn(Player $player) => $player->getBonusCount()
        );
        if (count($shields) === 0) {
            return 0;
        }
        return max($shields);
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

    /**
     * @template T of Team
     *
     * @param  T  $team
     * @return string
     */
    public function getBaseNameForTeam(Team $team) : string {
        // TODO: rewrite this to be more modular

        // The only variants are MZ and ZM
        $topTeam = str_ends_with($team->game->modeName, 'ZM') ? 1 : 2; // 1 = green, 2 = blue

        if ($team->color === $topTeam) {
            return lang('Horní základna', context: 'mode.zakladny', domain: 'results');
        }
        return lang('Dolní základna', context: 'mode.zakladny', domain: 'results');
    }
}

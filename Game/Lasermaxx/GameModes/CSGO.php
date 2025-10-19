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
use App\Gate\Screens\Results\LaserMaxxCSGOResultsScreen;
use Lsr\Lg\Results\Interface\Models\GameInterface;
use Lsr\Lg\Results\Interface\Models\TeamGameModeInterface;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Exceptions\ModelNotFoundException;

/**
 * Special LaserMaxx game mode
 */
#[PrimaryKey('id_mode')]
#[Factory(GameModeFactory::class)] // @phpstan-ignore-line
class CSGO extends AbstractMode implements CustomResultsMode, TeamGameModeInterface
{
    use LaserMaxxScores;


    public string $name = 'CSGO';

    /**
     * @template T of Team
     * @template P of Player
     * @template  G of Game<T, P>
     * @param  G  $game
     *
     * @return T|null
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function getWin(GameInterface $game) : ?Team {
        $teams = $game->teams;
        // Two teams - get the last team alive or team with most hits
        if (count($teams) === 2) {
            /** @var T $team1 */
            $team1 = $teams->first();
            $remaining1 = $this->getRemainingLives($team1);
            /** @var T $team2 */
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
        /** @var T $team */
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
     * @template T of Team
     * @param  T  $team
     *
     * @return int<0, max>
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function getRemainingLives(Team $team) : int {
        $remaining = $this->getTotalLives($team) - $team->getDeaths();
        assert($remaining >= 0);
        return $remaining;
    }

    /**
     * @template T of Team
     * @param  T  $team
     *
     * @return int<0, max>
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function getTotalLives(Team $team) : int {
        $game = $team->game;
        $total = count($team->players) * $game->lives;
        assert($total >= 0);
        return $total;
    }

    /**
     * @inheritDoc
     */
    public function getCustomResultsTemplate() : string {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function getCustomGateScreen() : string {
        return LaserMaxxCSGOResultsScreen::class;
    }
}

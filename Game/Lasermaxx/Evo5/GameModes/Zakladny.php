<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game\Lasermaxx\Evo5\GameModes;

use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\Lasermaxx\Evo5\Player;
use App\GameModels\Game\Lasermaxx\Evo5\Team;
use App\GameModels\Game\Lasermaxx\Team as LasermaxxTeam;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Exceptions\ModelNotFoundException;

/**
 * Special LaserMaxx Evo5 game mode
 */
#[PrimaryKey('id_mode')]
#[Factory(GameModeFactory::class)] // @phpstan-ignore-line
class Zakladny extends \App\GameModels\Game\Lasermaxx\GameModes\Zakladny
{
    /**
     * Get number of bases destroyed
     *
     * @param  Team  $team
     *
     * @return int
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function getBasesDestroyed(LasermaxxTeam $team) : int {
        $shields = $team->players->map(
          fn(Player $player) => $player->bonus->shield
        );
        if (count($shields) === 0) {
            return 0;
        }
        return max($shields);
    }
}

<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game\Lasermaxx\Evo6\GameModes;

use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\Lasermaxx\Evo6\Player;
use App\GameModels\Game\Lasermaxx\Evo6\Team;
use App\GameModels\Game\Lasermaxx\Team as LasermaxxTeam;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\PrimaryKey;

/**
 * Special LaserMaxx Evo6 game mode
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
     * @throws ValidationException
     */
    public function getBasesDestroyed(LasermaxxTeam $team) : int {
        $shields = $team->players->map(
        /** @phpstan-ignore argument.type */
          static fn(Player $player) => $player->getBonusCount()
        );
        if (count($shields) === 0) {
            return 0;
        }
        return max($shields);
    }
}

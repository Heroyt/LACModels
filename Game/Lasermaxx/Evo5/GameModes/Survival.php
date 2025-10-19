<?php

namespace App\GameModels\Game\Lasermaxx\Evo5\GameModes;

use App\GameModels\Factory\GameModeFactory;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\PrimaryKey;

/**
 * Special LaserMaxx Evo5 game mode
 */
#[PrimaryKey('id_mode')]
#[Factory(GameModeFactory::class)] // @phpstan-ignore-line
class Survival extends \App\GameModels\Game\Lasermaxx\GameModes\Survival
{
    public function getTeamAlternative() : string {
        return TeamSurvival::class;
    }
}

<?php

namespace App\GameModels\Game\Evo5\GameModes;

use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\Lasermaxx\GameModes\LaserMaxxScores;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\PrimaryKey;

/**
 * LaserMaxx Evo5 Deathmatch game mode
 */
#[PrimaryKey('id_mode')]
#[Factory(GameModeFactory::class)] // @phpstan-ignore-line
class Deathmatch extends \App\GameModels\Game\GameModes\Deathmatch
{
    use LaserMaxxScores;

    public function getTeamAlternative() : string {
        return TeamDeathmatch::class;
    }
}

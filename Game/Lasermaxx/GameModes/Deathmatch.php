<?php

namespace App\GameModels\Game\Lasermaxx\GameModes;

use App\GameModels\Factory\GameModeFactory;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\PrimaryKey;

/**
 * LaserMaxx Deathmatch game mode
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

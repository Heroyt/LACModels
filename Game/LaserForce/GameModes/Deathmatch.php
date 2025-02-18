<?php

namespace App\GameModels\Game\LaserForce\GameModes;

use App\GameModels\Factory\GameModeFactory;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\PrimaryKey;

/**
 * LaserForce Deathmatch game mode
 */
#[PrimaryKey('id_mode')]
#[Factory(GameModeFactory::class)] // @phpstan-ignore-line
class Deathmatch extends \App\GameModels\Game\GameModes\Deathmatch
{
    public function getTeamAlternative() : string {
        return TeamDeathmatch::class;
    }
}

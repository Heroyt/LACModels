<?php

namespace App\GameModels\Game\Evo5\GameModes;

use App\GameModels\Factory\GameModeFactory;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\PrimaryKey;

#[PrimaryKey('id_mode')]
#[Factory(GameModeFactory::class)] // @phpstan-ignore-line
class Deathmatch extends \App\GameModels\Game\Lasermaxx\GameModes\Deathmatch
{

    public function getTeamAlternative() : string {
        return TeamDeathmatch::class;
    }
}

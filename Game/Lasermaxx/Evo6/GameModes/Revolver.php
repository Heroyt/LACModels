<?php
declare(strict_types=1);

namespace App\GameModels\Game\Lasermaxx\Evo6\GameModes;

use App\GameModels\Factory\GameModeFactory;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\PrimaryKey;

#[
  PrimaryKey('id_mode'),
  Factory(GameModeFactory::class) // @phpstan-ignore argument.type
]
class Revolver extends Deathmatch
{
    public string $name = 'Revolver';

    public function getTeamAlternative() : string {
        return TeamRevolver::class;
    }

}
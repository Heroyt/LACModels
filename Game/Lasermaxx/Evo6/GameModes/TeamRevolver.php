<?php
declare(strict_types=1);

namespace App\GameModels\Game\Lasermaxx\Evo6\GameModes;

use App\GameModels\Factory\GameModeFactory;
use Lsr\Lg\Results\Enums\GameModeType;
use Lsr\Lg\Results\Interface\Models\TeamGameModeInterface;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\PrimaryKey;

#[
  PrimaryKey('id_mode'),
  Factory(GameModeFactory::class) // @phpstan-ignore argument.type
]
class TeamRevolver extends Revolver implements TeamGameModeInterface
{
    public GameModeType $type = GameModeType::TEAM;
    public string $name = 'Team Revolver';

    public function getSoloAlternative() : string {
        return Revolver::class;
    }

}
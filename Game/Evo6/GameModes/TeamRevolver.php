<?php
declare(strict_types=1);

namespace App\GameModels\Game\Evo6\GameModes;

use App\GameModels\Factory\GameModeFactory;
use Lsr\Lg\Results\Enums\GameModeType;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\PrimaryKey;

/** @phpstan-ignore argument.type */
#[PrimaryKey('id_mode'), Factory(GameModeFactory::class)]
class TeamRevolver extends Revolver
{
    public GameModeType $type = GameModeType::TEAM;
    public string $name = 'Team Revolver';

}
<?php
declare(strict_types=1);

namespace App\GameModels\Game\Evo6\GameModes;

use App\GameModels\Factory\GameModeFactory;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\PrimaryKey;

#[PrimaryKey('id_mode'), Factory(GameModeFactory::class)]
class Revolver extends Deathmatch
{
    public string $name = 'Revolver';

}
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
class KamenNuzkyPapir extends TeamDeathmatch
{

    public string $name = 'Kámen, Nůžky, Papír';

}
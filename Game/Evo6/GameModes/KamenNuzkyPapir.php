<?php
declare(strict_types=1);

namespace App\GameModels\Game\Evo6\GameModes;

use App\GameModels\Factory\GameModeFactory;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\PrimaryKey;

/** @phpstan-ignore argument.type */
#[PrimaryKey('id_mode'), Factory(GameModeFactory::class)]
class KamenNuzkyPapir extends TeamDeathmatch
{

    public string $name = 'Kámen, Nůžky, Papír';

}
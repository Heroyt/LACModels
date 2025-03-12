<?php

namespace App\GameModels\Game\Lasermaxx\GameModes;

use App\GameModels\Factory\GameModeFactory;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\PrimaryKey;

/**
 * LaserMaxx Evo5 Deathmatch game mode
 */
#[PrimaryKey('id_mode')]
#[Factory(GameModeFactory::class)] // @phpstan-ignore-line
abstract class Deathmatch extends \App\GameModels\Game\GameModes\Deathmatch
{
    use LaserMaxxScores;
}

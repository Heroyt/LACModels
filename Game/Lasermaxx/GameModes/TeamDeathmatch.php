<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game\Lasermaxx\GameModes;

use App\GameModels\Factory\GameModeFactory;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\PrimaryKey;

/**
 * LaserMaxx Evo5 team Deathmatch game mode
 */
#[PrimaryKey('id_mode')]
#[Factory(GameModeFactory::class)] // @phpstan-ignore-line
abstract class TeamDeathmatch extends \App\GameModels\Game\GameModes\TeamDeathmatch
{
    use LaserMaxxScores;
}

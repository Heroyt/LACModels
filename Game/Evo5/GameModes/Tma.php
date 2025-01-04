<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game\Evo5\GameModes;

use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\Lasermaxx\GameModes\LaserMaxxScores;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\PrimaryKey;

/**
 * Special LaserMaxx Evo5 game mode
 */
#[PrimaryKey('id_mode')]
#[Factory(GameModeFactory::class)] // @phpstan-ignore-line
class Tma extends TeamDeathmatch
{
    use LaserMaxxScores;


    public string $name = 'T.M.A.';
}

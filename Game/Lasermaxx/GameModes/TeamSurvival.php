<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game\Lasermaxx\GameModes;

use App\GameModels\Factory\GameModeFactory;
use Lsr\Lg\Results\Enums\GameModeType;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\PrimaryKey;

/**
 * Special LaserMaxx Evo5 game mode
 */
#[PrimaryKey('id_mode')]
#[Factory(GameModeFactory::class)] // @phpstan-ignore-line
abstract class TeamSurvival extends Survival
{
    use LaserMaxxScores;


    public string $name = 'Team Survival';
    public GameModeType $type = GameModeType::TEAM;
}

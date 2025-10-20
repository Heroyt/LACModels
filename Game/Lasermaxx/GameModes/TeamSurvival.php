<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game\Lasermaxx\GameModes;

use App\GameModels\Factory\GameModeFactory;
use Lsr\Lg\Results\Enums\GameModeType;
use Lsr\Lg\Results\Interface\Models\TeamGameModeInterface;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\PrimaryKey;

/**
 * Special LaserMaxx game mode
 */
#[PrimaryKey('id_mode')]
#[Factory(GameModeFactory::class)] // @phpstan-ignore-line
class TeamSurvival extends Survival implements TeamGameModeInterface
{
    use LaserMaxxScores;


    public string $name = 'Team Survival';
    public GameModeType $type = GameModeType::TEAM;

    public function getSoloAlternative() : string {
        return Survival::class;
    }
}

<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */
namespace App\GameModels\Game\Evo5\GameModes;

use App\GameModels\Game\Enums\GameModeType;

class TeamSurvival extends Survival
{

	public string       $name = 'Team Survival';
	public GameModeType $type = GameModeType::TEAM;

}
<?php

namespace App\GameModels\Game\Evo5\GameModes;

use App\GameModels\Game\Enums\GameModeType;

class TeamSurvival extends Survival
{

	public string       $name = 'Team Survival';
	public GameModeType $type = GameModeType::TEAM;

}
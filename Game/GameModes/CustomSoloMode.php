<?php

namespace App\GameModels\Game\GameModes;

use App\GameModels\Game\Enums\GameModeType;

class CustomSoloMode extends AbstractMode
{

	public GameModeType $type = GameModeType::SOLO;

}
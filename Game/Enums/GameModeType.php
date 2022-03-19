<?php

namespace App\GameModels\Game\Enums;

enum GameModeType: string
{
	case TEAM = 'TEAM';
	case SOLO = 'SOLO';
}
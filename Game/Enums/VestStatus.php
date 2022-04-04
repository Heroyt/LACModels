<?php

namespace App\GameModels\Game\Enums;

enum VestStatus: string
{

	case OK = 'ok';
	case PLAYABLE = 'playable';
	case BROKEN = 'broken';

}
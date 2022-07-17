<?php

namespace App\GameModels\Game\Enums;

/**
 * Statuses for vests
 *
 * @method static tryFrom(string $value)
 */
enum VestStatus: string
{

	case OK = 'ok';
	case PLAYABLE = 'playable';
	case BROKEN = 'broken';

}
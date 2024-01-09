<?php

namespace App\GameModels\Game\Enums;

/**
 * Statuses for vests
 *
 * @method static VestStatus|null tryFrom(string $value)
 * @method static VestStatus from(string $value)
 * @property string $value
 */
enum VestStatus: string
{

	case OK = 'ok';
	case PLAYABLE = 'playable';
	case BROKEN = 'broken';

}
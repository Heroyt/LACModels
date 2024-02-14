<?php

namespace App\GameModels\Game\Enums;

use OpenApi\Attributes as OA;

/**
 * Statuses for vests
 *
 * @property string $value
 * @method static VestStatus|null tryFrom(string $value)
 * @method static VestStatus from(string $value)
 */
#[OA\Schema(type: 'string')]
enum VestStatus: string
{

	case OK = 'ok';
	case PLAYABLE = 'playable';
	case BROKEN = 'broken';

}
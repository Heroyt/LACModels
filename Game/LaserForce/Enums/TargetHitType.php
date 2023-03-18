<?php

namespace App\GameModels\Game\LaserForce\Enums;

/**
 * @property string $value
 */
enum TargetHitType: string
{

	case HIT = 'hit';
	case DESTROYED = 'destroyed';

}

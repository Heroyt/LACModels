<?php

namespace App\GameModels\Game\LaserForce;

/**
 * @extends \App\GameModels\Game\Team<Player, Game>
 */
class Team extends \App\GameModels\Game\Team
{

	public const TABLE  = 'laserforce_teams';
	public const SYSTEM = 'laserForce';
	public int $index = 0;

}
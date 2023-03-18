<?php

namespace App\GameModels\Game\LaserForce;

use App\GameModels\Traits\WithGame;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\NoDB;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;

#[PrimaryKey('id_target')]
class Target extends Model
{
	use WithGame;

	public const TABLE  = 'laserforce_targets';
	public const SYSTEM = 'laserforce';

	public const CACHE_TAGS = ['targets', 'games/laserforce', 'targets/laserforce'];

	public string $identifier = '';
	public string $name       = '';

	#[ManyToOne(foreignKey: 'id_team')]
	public ?Team $team    = null;
	#[NoDB]
	public int   $teamNum = 0;

	/**
	 * @return Team|null
	 */
	public function getTeam() : ?Team {
		return $this->team;
	}

	/**
	 * @param Team $team
	 *
	 * @return Target
	 */
	public function setTeam(Team $team) : Target {
		$this->team = $team;
		return $this;
	}

}
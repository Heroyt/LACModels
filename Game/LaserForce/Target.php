<?php

namespace App\GameModels\Game\LaserForce;

use App\GameModels\Traits\WithGame;
use App\Models\BaseModel;
use Lsr\Orm\Attributes\NoDB;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;

#[PrimaryKey('id_target')]
class Target extends BaseModel
{
    use WithGame;

    public const string TABLE = 'laserforce_targets';
    public const SYSTEM = 'laserforce';

    public const CACHE_TAGS = ['targets', 'games/laserforce', 'targets/laserforce'];

    public string $identifier = '';
    public string $name = '';

    #[ManyToOne(foreignKey: 'id_team')]
    public ?Team $team = null;
    #[NoDB]
    public int $teamNum = 0;

    /**
     * @return Team|null
     */
    public function getTeam() : ?Team {
        return $this->team;
    }

    /**
     * @param  Team  $team
     *
     * @return Target
     */
    public function setTeam(Team $team) : Target {
        $this->team = $team;
        return $this;
    }
}

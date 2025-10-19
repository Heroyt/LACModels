<?php

namespace App\GameModels\Game\LaserForce;

use App\GameModels\Traits\WithGame;
use App\Models\BaseModel;
use Dibi\Row;
use Lsr\Orm\Attributes\NoDB;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;

#[PrimaryKey('id_target')]
class Target extends BaseModel
{
    /**
     * @use WithGame<Game>
     */
    use WithGame;

    public const string TABLE = 'laserforce_targets';
    public const string SYSTEM = 'laserforce';

    public string $identifier = '';
    public string $name = '';

    #[ManyToOne(foreignKey: 'id_team')]
    public ?Team $team = null;
    #[NoDB]
    public int $teamNum = 0;

    public function __construct(?int $id = null, ?Row $dbRow = null) {
        parent::__construct($id, $dbRow);
        $this->cacheTags[] = 'targets';
        $this->cacheTags[] = 'games/laserforce';
        $this->cacheTags[] = 'targets/laserforce';
    }

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

<?php

namespace App\GameModels\Game\Lasermaxx\Evo6;

use App\GameModels\Factory\PlayerFactory;
use App\Models\Auth\Player as User;
use Lsr\Lg\Results\Interface\Models\GameInterface;
use Lsr\Lg\Results\Interface\Models\TeamInterface;
use Lsr\Lg\Results\LaserMaxx\Evo6\Evo6PlayerInterface;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\NoDB;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;

/**
 * LaserMaxx Evo6 player model
 *
 * @extends \App\GameModels\Game\Lasermaxx\Player<Game, Team>
 * @implements Evo6PlayerInterface<Game, Team, User>
 */
#[PrimaryKey('id_player'), Factory(PlayerFactory::class, ['system' => 'evo6'])]
class Player extends \App\GameModels\Game\Lasermaxx\Player implements Evo6PlayerInterface
{
    public const string TABLE = 'evo6_players';
    public const string SYSTEM = 'evo6';

    public int $bonuses = 0;
    public int $activity = 0;
    public int $calories = 0;
    public int $scoreActivity = 0;
    public int $scoreEncouragement = 0;
    public int $scoreKnockout = 0;
    public int $scorePenalty = 0;
    public int $scoreReality = 0;
    public int $penaltyCount = 0;
    public bool $birthday = false;
    #[NoDB]
    public int $respawns {
        get {
            if ($this->deaths < $this->game->lives) {
                return 0;
            }
            return (int) floor(($this->deaths - $this->game->lives) / $this->game->respawnSettings->respawnLives);
        }
    }

    #[ManyToOne(class: Game::class)]
    public GameInterface $game;
    #[ManyToOne(foreignKey: 'id_team', class: Team::class)]
    public ?TeamInterface $team = null;

    /**
     * @inheritDoc
     */
    public function getMines() : int {
        return $this->bonuses;
    }

    public function getBonusCount() : int {
        return $this->bonuses;
    }

}

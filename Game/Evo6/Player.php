<?php

namespace App\GameModels\Game\Evo6;

use App\GameModels\Factory\PlayerFactory;
use App\GameModels\Game\Game as BaseGame;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;

/**
 * LaserMaxx Evo6 player model
 *
 * @extends \App\GameModels\Game\Lasermaxx\Player<Game, Team>
 * @phpstan-ignore-next-line
 */
#[PrimaryKey('id_player'), Factory(PlayerFactory::class, ['system' => 'evo6'])]
class Player extends \App\GameModels\Game\Lasermaxx\Player
{
    public const string TABLE = 'evo6_players';
    public const string SYSTEM = 'evo6';

    public int $bonuses = 0;
    public int $calories = 0;

    #[ManyToOne(class: Game::class)]
    public BaseGame $game;
    #[ManyToOne(foreignKey: 'id_team', class: Team::class)]
    public ?\App\GameModels\Game\Team $team = null;

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

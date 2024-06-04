<?php

namespace App\GameModels\Game\Evo6;

use App\GameModels\Factory\PlayerFactory;
use App\GameModels\Game\Game as BaseGame;
use Lsr\Core\Models\Attributes\Factory;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\LoadingType;

/**
 * LaserMaxx Evo6 player model
 *
 * @extends \App\GameModels\Game\Lasermaxx\Player<Game, Team>
 * @phpstan-ignore-next-line
 */
#[PrimaryKey('id_player'), Factory(PlayerFactory::class, ['system' => 'evo6'])]
class Player extends \App\GameModels\Game\Lasermaxx\Player
{

	public const TABLE  = 'evo6_players';
	public const SYSTEM = 'evo6';

	public int $bonuses  = 0;
	public int $calories = 0;

	#[ManyToOne(class: Game::class, loadingType: LoadingType::LAZY)]
	public BaseGame                   $game;
	#[ManyToOne(foreignKey: 'id_team', class: Team::class)]
	public ?\App\GameModels\Game\Team $team = null;

	/**
	 * @inheritDoc
	 */
	public function getMines(): int {
		return $this->bonuses;
	}

    public function getBonusCount() : int {
        return $this->bonuses;
    }
}
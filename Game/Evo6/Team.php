<?php

namespace App\GameModels\Game\Evo6;


use App\GameModels\Factory\TeamFactory;
use Lsr\Core\Models\Attributes\Factory;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\NoDB;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\LoadingType;

/**
 * LaserMaxx Evo6 team model
 *
 * @extends \App\GameModels\Game\Lasermaxx\Team<Player, Game>
 * @phpstan-ignore-next-line
 */
#[PrimaryKey('id_team'), Factory(TeamFactory::class, ['system' => 'evo6'])]
class Team extends \App\GameModels\Game\Lasermaxx\Team
{

	public const TABLE  = 'evo6_teams';
	public const SYSTEM = 'evo6';

	/** @var class-string<Player> */
	#[NoDB]
	public string $playerClass = Player::class;

	/** @var Game */
	#[ManyToOne(class: Game::class, loadingType: LoadingType::LAZY)]
	public \App\GameModels\Game\Game $game;

}
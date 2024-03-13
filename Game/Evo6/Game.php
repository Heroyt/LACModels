<?php

namespace App\GameModels\Game\Evo6;

use App\GameModels\Factory\GameFactory;
use Lsr\Core\Models\Attributes\Factory;
use Lsr\Core\Models\Attributes\Instantiate;
use Lsr\Core\Models\Attributes\NoDB;
use Lsr\Core\Models\Attributes\PrimaryKey;
use OpenApi\Attributes as OA;

/**
 * LaserMaxx Evo6 game model
 *
 * @extends \App\GameModels\Game\Lasermaxx\Game<Team, Player>
 * @phpstan-ignore-next-line
 */
#[PrimaryKey('id_game'), Factory(GameFactory::class, ['system' => 'evo6'])]
class Game extends \App\GameModels\Game\Lasermaxx\Game
{

	public const SYSTEM = 'evo6';
	public const TABLE  = 'evo6_games';

	#[NoDB]
	public string                             $playerClass = Player::class;
	#[NoDB]
	public string                             $teamClass   = Team::class;
	#[Instantiate]
	#[OA\Property]
	public Scoring $scoring;

}
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

	public const string TABLE  = 'evo6_players';
	public const string SYSTEM = 'evo6';

	protected const array IMPORT_PROPERTIES = [
		'name',
		'score',
		'skill',
		'vest',
		'shots',
		'accuracy',
		'hits',
		'deaths',
		'position',
		'hitsOther',
		'hitsOwn',
		'deathsOther',
		'deathsOwn',
		'shotPoints',
		'scoreBonus',
		'scorePowers',
		'scoreMines',
		'ammoRest',
		'minesHits',
		'vip',
		'myLasermaxx',
		'bonuses',
		'calories',
	];

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
}
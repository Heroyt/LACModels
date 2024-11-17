<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game\Evo5;

use App\GameModels\Factory\PlayerFactory;
use App\GameModels\Game\Game as BaseGame;
use Lsr\Core\Models\Attributes\Factory;
use Lsr\Core\Models\Attributes\Instantiate;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\LoadingType;

/**
 * LaserMaxx Evo5 player model
 *
 * @extends \App\GameModels\Game\Lasermaxx\Player<Game, Team>
 * @phpstan-ignore-next-line
 */
#[PrimaryKey('id_player'), Factory(PlayerFactory::class, ['system' => 'evo5'])]
class Player extends \App\GameModels\Game\Lasermaxx\Player
{

	public const string TABLE  = 'evo5_players';
	public const string SYSTEM = 'evo5';

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
		'bonus',
	];

	#[Instantiate]
	public BonusCounts                $bonus;
	#[ManyToOne(class: Game::class, loadingType: LoadingType::LAZY)]
	public BaseGame                   $game;
	#[ManyToOne(foreignKey: 'id_team', class: Team::class)]
	public ?\App\GameModels\Game\Team $team = null;

	public function getMines(): int {
		return $this->bonus->getSum();
	}

}
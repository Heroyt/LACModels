<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game\Evo5;

use App\GameModels\Factory\PlayerFactory;
use App\GameModels\Game\Game;
use Lsr\Core\Models\Attributes\Factory;
use Lsr\Core\Models\Attributes\Instantiate;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;

/**
 * LaserMaxx Evo5 player model
 */
#[PrimaryKey('id_player')]
#[Factory(PlayerFactory::class, ['system' => 'evo5'])]
class Player extends \App\GameModels\Game\Player
{

	public const TABLE         = 'evo5_players';
	public const CLASSIC_BESTS = ['score', 'hits', 'score', 'accuracy', 'shots', 'miss', 'hitsOwn', 'deathsOwn', 'mines'];
	public int                           $shotPoints  = 0;
	public int                           $scoreBonus  = 0;
	public int                           $scorePowers = 0;
	public int                           $scoreMines  = 0;
	public int                           $ammoRest    = 0;
	public int                           $minesHits   = 0;
	#[Instantiate]
	public BonusCounts                   $bonus;
	public int                           $hitsOther   = 0;
	public int                           $hitsOwn     = 0;
	public int                           $deathsOwn   = 0;
	public int                           $deathsOther = 0;
	#[ManyToOne(class: \App\GameModels\Game\Evo5\Game::class)]
	public ?Game                         $game;
	#[ManyToOne(foreignKey: 'id_team', class: Team::class)]
	protected ?\App\GameModels\Game\Team $team        = null;

	public function getMines() : int {
		return $this->bonus->getSum();
	}

	public function getRemainingLives() : int {
		return ($this->getGame()->lives ?? 9999) - $this->deaths;
	}

}
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
#[Factory(PlayerFactory::class, ['system' => 'evo5'])] // @phpstan-ignore-line
class Player extends \App\GameModels\Game\Player
{

	public const TABLE         = 'evo5_players';
	public const CLASSIC_BESTS = ['score', 'hits', 'score', 'accuracy', 'shots', 'miss', 'hitsOwn', 'deathsOwn', 'mines'];
	public int                        $shotPoints  = 0;
	public int                        $scoreBonus  = 0;
	public int                        $scorePowers = 0;
	public int                        $scoreMines  = 0;
	public int                        $ammoRest    = 0;
	public int                        $minesHits   = 0;
	#[Instantiate]
	public BonusCounts                $bonus;
	public int                        $hitsOther   = 0;
	public int                        $hitsOwn     = 0;
	public int                        $deathsOwn   = 0;
	public int                        $deathsOther = 0;
	#[ManyToOne(class: \App\GameModels\Game\Evo5\Game::class)]
	public Game                       $game;
	#[ManyToOne(foreignKey: 'id_team', class: Team::class)]
	public ?\App\GameModels\Game\Team $team        = null;

	public function getMines() : int {
		return $this->bonus->getSum();
	}

	public function getRemainingLives() : int {
		return ($this->getGame()->lives ?? 9999) - $this->deaths;
	}

	/**
	 * Calculate a skill level based on the player's results
	 *
	 * The skill value aims to better evaluate the player's play style than the regular score value.
	 * It should take multiple metrics into account.
	 * Other LG system implementations should modify this function to calculate the value based on its specific metrics.
	 * The value must be normalized based on the game's length.
	 *
	 * @pre The player's results should be set.
	 *
	 * @return int A whole number evaluation on an arbitrary scale (no max or min value).
	 */
	public function calculateSkill() : int {
		// Base skill value - hits, K:D, K:D deviation, accuracy - already normalized
		$skill = $this->calculateBaseSkill();

		$newSkill = 0;

		// Remove points for each teammate hit
		$newSkill -= $this->hitsOwn * 2;

		// Add points for bonuses
		$newSkill += $this->bonus->getSum();

		// Add points for accuracy
		$newSkill += 5 * ($this->accuracy / 100);

		// Normalize based on the game's length
		$gameLength = $this->getGame()->getRealGameLength();
		if ($gameLength !== 0.0) {
			$newSkill *= 15 / $gameLength;
		}

		$this->skill = (int) round($skill + $newSkill);

		return $this->skill;
	}

	public function getKd() : float {
		return $this->hitsOther / ($this->deathsOther === 0 ? 1 : $this->deathsOther);
	}

}
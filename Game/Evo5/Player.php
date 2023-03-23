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
use Throwable;

/**
 * LaserMaxx Evo5 player model
 */
#[PrimaryKey('id_player')]
#[Factory(PlayerFactory::class, ['system' => 'evo5'])] // @phpstan-ignore-line
class Player extends \App\GameModels\Game\Player
{

	public const TABLE         = 'evo5_players';
	public const SYSTEM        = 'evo5';
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

	public bool $vip = false;

	public function getMines() : int {
		return $this->bonus->getSum();
	}

	public function getRemainingLives() : int {
		return ($this->getGame()->lives ?? 9999) - $this->deaths;
	}

	/**
	 * Get an expected number of teammates hit based on the number of teammates and enemies.
	 *
	 * Based on data collected, players hits on average 0.8 teammates per teammate with 0.8 standard deviation.
	 * We used regression to calculate the best model to describe the best model to predict the average number of hits based on the player's enemy and teammate count.
	 * We can easily calculate the expected average hit count for each player.
	 *
	 * @return float
	 * @throws Throwable
	 */
	public function getExpectedAverageTeammateHitCount() : float {
		$enemyPlayerCount = $this->getGame()->getPlayerCount() - ($this->getTeam()?->getPlayerCount() ?? 1);
		$teamPlayerCount = $this->getTeam()?->getPlayerCount() - 1;
		return ($enemyPlayerCount * 0.21216) + ($teamPlayerCount * 0.42801) + 1.34791;
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
	 * @throws Throwable
	 */
	public function calculateSkill() : int {
		// Base skill value - hits, K:D, K:D deviation, accuracy - already normalized
		$skill = $this->calculateBaseSkill();

		$newSkill = 0;

		$newSkill += $this->calculateSkillFromTeamHits();

		// Add points for bonuses
		$newSkill += $this->calculateSkillFromBonuses();

		$this->skill = (int) round($skill + $newSkill);

		return $this->skill;
	}

	public function getKd() : float {
		return $this->game->mode?->isSolo() ?
			parent::getKd() :
			$this->hitsOther / ($this->deathsOther === 0 ? 1 : $this->deathsOther);
	}

	/**
	 * @return float
	 * @throws Throwable
	 */
	protected function calculateSkillFromTeamHits() : float {
		if ($this->game->mode?->isTeam() ?? true) {
			$expectedAverageHits = $this->getExpectedAverageTeammateHitCount();
			$hitsDiff = $this->hitsOwn - $expectedAverageHits;

			// Normalize to value between <0,...) where the value of 1 corresponds to exactly average hit count
			$hitsDiffPercent = 1 + ($hitsDiff / $expectedAverageHits);

			// Completely average game should lose at least 100 points
			// On the other hand -> hitting no teammates will grant 100 points
			$hitsSkill = $hitsDiffPercent * 100;
		}
		else {
			// In solo game, no teammates can be hit. Adds 100 points as a base.
			$hitsSkill = 0;
		}

		// Normalize based on the game's length
		$gameLength = $this->getGame()->getRealGameLength();
		if ($gameLength !== 0.0) {
			$hitsSkill *= 15 / $gameLength;
		}
		return -$hitsSkill;
	}

	/**
	 * @return float
	 */
	protected function calculateSkillFromBonuses() : float {
		return $this->bonus->getSum() * 10;
	}

	public function getSkillParts() : array {
		$parts = parent::getSkillParts();
		$parts['teamHits'] = $this->calculateSkillFromTeamHits();
		$parts['bonuses'] = $this->calculateSkillFromBonuses();
		return $parts;
	}

}
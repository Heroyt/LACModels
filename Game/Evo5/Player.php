<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game\Evo5;

use App\GameModels\Factory\PlayerFactory;
use App\GameModels\Game\Enums\GameModeType;
use App\GameModels\Game\Game as BaseGame;
use App\GameModels\Tools\Evo5\RegressionStatCalculator;
use App\Services\RegressionCalculator;
use Lsr\Core\Models\Attributes\Factory;
use Lsr\Core\Models\Attributes\Instantiate;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Throwable;

/**
 * LaserMaxx Evo5 player model
 *
 * @extends \App\GameModels\Game\Player<Game, Team>
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
	#[ManyToOne(class: Game::class)]
	public BaseGame                   $game;
	#[ManyToOne(foreignKey: 'id_team', class: Team::class)]
	public ?\App\GameModels\Game\Team $team        = null;

	public bool $vip = false;

	private RegressionStatCalculator $calculator;

	public function getMines() : int {
		return $this->bonus->getSum();
	}

	public function getRemainingLives() : int {
		return ($this->getGame()->lives ?? 9999) - $this->deaths;
	}

	/**
	 * Get an expected number of deaths based on the number of teammates and enemies.
	 *
	 * We used regression to calculate the best model to describe the best model to predict the average number of hits based on the player's enemy and teammate count.
	 * We can easily calculate the expected average death count for each player.
	 *
	 * @return float
	 * @throws Throwable
	 */
	public function getExpectedAverageDeathCount() : float {
		$type = $this->getGame()->gameType;
		$model = $this->getRegressionCalculator()->getDeathsModel(
			$type,
			$this->getGame()->getMode(),
			$type === GameModeType::TEAM ? $this->getGame()
			                                    ->getTeams()
			                                    ->count() : 2
		);
		return $this->calculateHitDeathModel($type, $model);
	}

	/**
	 * @return RegressionStatCalculator
	 */
	public function getRegressionCalculator() : RegressionStatCalculator {
		if (!isset($this->calculator)) {
			$this->calculator = new RegressionStatCalculator($this->getGame()->arena);
		}
		return $this->calculator;
	}

	/**
	 * @param GameModeType $type
	 * @param array        $model
	 *
	 * @return float
	 * @throws Throwable
	 */
	private function calculateHitDeathModel(GameModeType $type, array $model) : float {
		$length = $this->getGame()->getRealGameLength();
		if ($type === GameModeType::TEAM) {
			$teamPlayerCount = $this->getTeam()?->getPlayerCount() ?? 0;
			$enemyPlayerCount = $this->getGame()->getPlayerCount() - $teamPlayerCount - 1;
			return RegressionCalculator::calculateRegressionPrediction([$teamPlayerCount, $enemyPlayerCount, $length], $model);
		}
		$enemyPlayerCount = $this->getGame()->getPlayerCount() - 1;
		return RegressionCalculator::calculateRegressionPrediction([$enemyPlayerCount, $length], $model);
	}

	/**
	 * Get an expected number of teammates deaths based on the number of teammates and enemies.
	 *
	 * Based on data collected.
	 * We used regression to calculate the best model to describe the best model to predict the average number of deaths based on the player's enemy and teammate count.
	 * We can easily calculate the expected average death count for each player.
	 *
	 * @return float
	 * @throws Throwable
	 */
	public function getExpectedAverageTeammateDeathCount() : float {
		$model = $this->getRegressionCalculator()->getDeathsOwnModel(
			$this->getGame()->getMode(),
			$this->getGame()->getTeams()->count()
		);
		$length = $this->getGame()->getRealGameLength();
		$enemyPlayerCount = $this->getGame()->getPlayerCount() - ($this->getTeam()?->getPlayerCount() ?? 1);
		$teamPlayerCount = $this->getTeam()?->getPlayerCount() - 1;
		return RegressionCalculator::calculateRegressionPrediction([$teamPlayerCount, $enemyPlayerCount, $length], $model);
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

		$newSkill += ($teamHitsSkill = $this->calculateSkillFromTeamHits());

		// Add points for bonuses
		$newSkill += ($bonusesSkill = $this->calculateSkillFromBonuses());

		$this->skill = (int) round($skill + $newSkill);

		return $this->skill;
	}

	/**
	 * @return float
	 * @throws Throwable
	 */
	protected function calculateSkillFromTeamHits() : float {
		if ($this->game->mode?->isTeam() ?? true) {
			$expectedAverageHits = $this->getExpectedAverageTeammateHitCount();
			if ($expectedAverageHits < 1.0) {
				$expectedAverageHits = 1.0;
			}
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

		return -$hitsSkill;
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
		$model = $this->getRegressionCalculator()->getHitsOwnModel(
			$this->getGame()->getMode(),
			$this->getGame()->getTeams()->count()
		);
		$length = $this->getGame()->getRealGameLength();
		$enemyPlayerCount = $this->getGame()->getPlayerCount() - ($this->getTeam()?->getPlayerCount() ?? 1);
		$teamPlayerCount = $this->getTeam()?->getPlayerCount() - 1;
		return RegressionCalculator::calculateRegressionPrediction([$teamPlayerCount, $enemyPlayerCount, $length], $model);
	}

	/**
	 * @return float
	 */
	protected function calculateSkillFromBonuses() : float {
		return $this->bonus->getSum() * 10;
	}

	public function getKd() : float {
		return $this->game->mode?->isSolo() ?
			parent::getKd() :
			$this->hitsOther / ($this->deathsOther === 0 ? 1 : $this->deathsOther);
	}

	public function getSkillParts() : array {
		$parts = parent::getSkillParts();
		$parts['teamHits'] = $this->calculateSkillFromTeamHits();
		$parts['bonuses'] = $this->calculateSkillFromBonuses();
		return $parts;
	}

	public function getExpectedAverageHitCount() : float {
		$type = $this->getTeam()?->getPlayerCount() === 1 ? GameModeType::SOLO : $this->getGame()->gameType;
		$model = $this->getRegressionCalculator()->getHitsModel(
			$type,
			$this->getGame()->getMode(),
			$type === GameModeType::TEAM ? $this->getGame()
			                                    ->getTeams()
			                                    ->count() : 2
		);
		return $this->calculateHitDeathModel($type, $model);
	}

}
<?php

namespace App\GameModels\Game\Lasermaxx;

use App\Exceptions\GameModeNotFoundException;
use App\Exceptions\InsuficientRegressionDataException;
use App\GameModels\Game\Enums\GameModeType;
use App\GameModels\Tools\Lasermaxx\RegressionStatCalculator;
use App\Services\RegressionCalculator;
use Throwable;

/**
 * LaserMaxx player model
 *
 * @template G of Game
 * @template T of Team
 *
 * @extends \App\GameModels\Game\Player<G, T>
 */
abstract class Player extends \App\GameModels\Game\Player
{

	public const CLASSIC_BESTS = [
		'score',
		'hits',
		'score',
		'accuracy',
		'shots',
		'miss',
		'hitsOwn',
		'deathsOwn',
		'mines',
	];

	public int $shotPoints  = 0;
	public int $scoreBonus  = 0;
	public int $scorePowers = 0;
	public int $scoreMines  = 0;
	public int $ammoRest    = 0;
	public int $minesHits   = 0;

	public int $hitsOther   = 0;
	public int $hitsOwn     = 0;
	public int $deathsOwn   = 0;
	public int $deathsOther = 0;

	public bool $vip = false;

	public string $myLasermaxx = '';

	protected RegressionStatCalculator $calculator;

	/**
	 * Get an expected number of deaths based on the number of teammates and enemies.
	 *
	 * We used regression to calculate the best model to describe the best model to predict the average number of hits based on the player's enemy and teammate count.
	 * We can easily calculate the expected average death count for each player.
	 *
	 * @return float
	 * @throws Throwable
	 */
	public function getExpectedAverageDeathCount(): float {
		$type = $this->getGame()->gameType;
      try {
          $model = $this->getRegressionCalculator()->getDeathsModel($type, $this->getGame()->getMode());
          return $this->calculateHitDeathModel($type, $model);
      } catch (InsuficientRegressionDataException $e) {
          $this->getLogger()->exception($e);
          return parent::getExpectedAverageDeathCount();
      }
	}

	/**
	 * @return RegressionStatCalculator
	 */
	public function getRegressionCalculator(): RegressionStatCalculator {
		if (!isset($this->calculator)) {
			$this->calculator = new RegressionStatCalculator();
		}
		return $this->calculator;
	}

	/**
	 * @param GameModeType $type
	 * @param numeric[]    $model
	 *
	 * @return float
	 * @throws Throwable
	 */
	private function calculateHitDeathModel(GameModeType $type, array $model): float {
		$length = $this->getGame()->getRealGameLength();
		if ($type === GameModeType::TEAM) {
			$teamPlayerCount = $this->getTeam()?->getPlayerCount() ?? 0;
			$enemyPlayerCount = $this->getGame()->getPlayerCount() - $teamPlayerCount - 1;
			return RegressionCalculator::calculateRegressionPrediction(
				[$teamPlayerCount, $enemyPlayerCount, $length],
				$model
			);
		}
		$enemyPlayerCount = $this->getGame()->getPlayerCount() - 1;
		return RegressionCalculator::calculateRegressionPrediction([$enemyPlayerCount, $length], $model);
	}

	public function getRemainingLives(): int {
		return ($this->getGame()->lives ?? 9999) - $this->deaths;
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
	public function getExpectedAverageTeammateDeathCount(): float {
      try {
          $model = $this->getRegressionCalculator()->getDeathsOwnModel($this->getGame()->getMode());
          $length = $this->getGame()->getRealGameLength();
          $enemyPlayerCount = $this->getGame()->getPlayerCount() - ($this->getTeam()?->getPlayerCount() ?? 1);
          $teamPlayerCount = $this->getTeam()?->getPlayerCount() - 1;
          return RegressionCalculator::calculateRegressionPrediction(
            [$teamPlayerCount, $enemyPlayerCount, $length],
            $model
          );
      } catch (InsuficientRegressionDataException $e) {
          $this->getLogger()->exception($e);
          return 0.0;
      }
	}

	public function getSkillParts(): array {
		$parts = parent::getSkillParts();
		try {
			$parts['teamHits'] = $this->calculateSkillFromTeamHits();
		} catch (InsuficientRegressionDataException) {
			// Ignore
		}
		$parts['bonuses'] = $this->calculateSkillFromBonuses();
		return $parts;
	}

	/**
	 * @return float
	 * @throws Throwable
	 */
	protected function calculateSkillFromTeamHits(): float {
		if ($this->getGame()->getMode()?->isTeam() ?? true) {
			$expectedAverageHits = $this->getExpectedAverageTeammateHitCount();
			if ($expectedAverageHits === 0.0) {
				$expectedAverageHits = 0.1;
			}
			$hitsDiff = $this->hitsOwn - $expectedAverageHits;

			// Normalize to value between <0,...) where the value of 1 corresponds to exactly average hit count
			$hitsDiffPercent = 1 + ($hitsDiff / (((int)$expectedAverageHits) !== 0 ? $expectedAverageHits : 1));

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
	 * Get an expected number of teammates hit based on the number of teammates and enemies.
	 *
	 * Based on data collected, players hits on average 0.8 teammates per teammate with 0.8 standard deviation.
	 * We used regression to calculate the best model to describe the best model to predict the average number of hits based on the player's enemy and teammate count.
	 * We can easily calculate the expected average hit count for each player.
	 *
	 * @return float
	 * @throws Throwable
	 */
	public function getExpectedAverageTeammateHitCount(): float {
      try {
          $model = $this->getRegressionCalculator()->getHitsOwnModel($this->getGame()->getMode());
          $length = $this->getGame()->getRealGameLength();
          $enemyPlayerCount = $this->getGame()->getPlayerCount() - ($this->getTeam()?->getPlayerCount() ?? 1);
          $teamPlayerCount = $this->getTeam()?->getPlayerCount() - 1;
          return RegressionCalculator::calculateRegressionPrediction(
            [$teamPlayerCount, $enemyPlayerCount, $length],
            $model
          );
      } catch (InsuficientRegressionDataException $e) {
          $this->getLogger()->exception($e);
          return 0.0;
      }
	}

	/**
	 * @return float
	 */
	protected function calculateSkillFromBonuses(): float {
		return $this->getMines() * 10;
	}

	/**
	 * Get bonus count
	 *
	 * @return int
	 */
	abstract public function getMines(): int;

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
	public function calculateSkill(): int {
		// Base skill value - hits, K:D, K:D deviation, accuracy - already normalized
		$skill = $this->calculateBaseSkill();

		$newSkill = 0;

		$newSkill += $this->calculateSkillFromTeamHits();

		// Add points for bonuses
		$newSkill += $this->calculateSkillFromBonuses();

		$this->skill = (int)round($skill + $newSkill);

		return $this->skill;
	}

	/**
	 * @return float
	 */
	public function getKd(): float {
		try {
			return $this->getGame()->getMode()?->isSolo() ? parent::getKd(
			) : $this->hitsOther / ($this->deathsOther === 0 ? 1 : $this->deathsOther);
		} catch (GameModeNotFoundException|Throwable) {
			return parent::getKd();
		}
	}

	protected function calculateSkillForHits(): float {
		$expectedAverageHits = $this->getExpectedAverageHitCount();
		$hitsDiff = $this->hits - $expectedAverageHits;

		// Normalize to value between <0,...) where the value of 1 corresponds to exactly average hit count
		$hitsDiffPercent = 1 + ($hitsDiff / (((int)$expectedAverageHits) !== 0 ? $expectedAverageHits : 1));

		// Completely average game should acquire at least 200 points
		return $hitsDiffPercent * 200;
	}

	public function getExpectedAverageHitCount(): float {
      try {
          $type = $this->getGame()->gameType;
          $model = $this->getRegressionCalculator()->getHitsModel($type, $this->getGame()->getMode());
          return $this->calculateHitDeathModel($type, $model);
      } catch (InsuficientRegressionDataException $e) {
          $this->getLogger()->exception($e);
          return parent::getExpectedAverageHitCount();
      }
	}
}
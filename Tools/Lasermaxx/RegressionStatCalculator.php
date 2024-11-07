<?php

namespace App\GameModels\Tools\Lasermaxx;

use App\Core\Info;
use App\Exceptions\InsuficientRegressionDataException;
use App\GameModels\Game\Enums\GameModeType;
use App\GameModels\Game\GameModes\AbstractMode;
use App\Models\Arena;
use App\Services\Maths\RegressionCalculator;
use Dibi\Exception;
use Dibi\Row;
use Lsr\Core\DB;
use Lsr\Core\Dibi\Fluent;

/**
 * Regression calculator class used for predicting player's hits, deaths, team hits and team deaths
 */
class RegressionStatCalculator
{
	private RegressionCalculator $regressionCalculator;

	public function __construct(private readonly ?Arena $arena = null) {
		$this->regressionCalculator = new RegressionCalculator();
	}

	/**
	 * @param Arena[]        $arenas
	 * @param AbstractMode[] $modes
	 *
	 * @return void
	 */
	public static function updateAll(array $arenas, array $modes): void {
		foreach ($arenas as $arena) {
			$calculator = new RegressionStatCalculator($arena);

			try {
				$calculator->updateHitsModel(GameModeType::SOLO);
			} catch (InsuficientRegressionDataException) {
			}
			try {
				$calculator->updateDeathsModel(GameModeType::SOLO);
			} catch (InsuficientRegressionDataException) {
			}
			for ($teamCount = 2; $teamCount < 7; $teamCount++) {
				try {
					$calculator->updateHitsModel(GameModeType::TEAM, teamCount: $teamCount);
				} catch (InsuficientRegressionDataException) {
				}
				try {
					$calculator->updateDeathsModel(GameModeType::TEAM, teamCount: $teamCount);
				} catch (InsuficientRegressionDataException) {
				}
				try {
					$calculator->updateHitsOwnModel(teamCount: $teamCount);
				} catch (InsuficientRegressionDataException) {
				}
				try {
					$calculator->updateDeathsOwnModel(teamCount: $teamCount);
				} catch (InsuficientRegressionDataException) {
				}
			}
			foreach ($modes as $mode) {
				if ($mode->type === GameModeType::TEAM) {
					for ($teamCount = 2; $teamCount < 7; $teamCount++) {
						try {
							$calculator->updateHitsModel($mode->type, $mode, $teamCount);
						} catch (InsuficientRegressionDataException) {
						}
						try {
							$calculator->updateDeathsModel($mode->type, $mode, $teamCount);
						} catch (InsuficientRegressionDataException) {
						}
						try {
							$calculator->updateHitsOwnModel($mode, $teamCount);
						} catch (InsuficientRegressionDataException) {
						}
						try {
							$calculator->updateDeathsOwnModel($mode, $teamCount);
						} catch (InsuficientRegressionDataException) {
						}
					}
				}
				else {
					try {
						$calculator->updateHitsModel($mode->type, $mode);
					} catch (InsuficientRegressionDataException) {
					}
					try {
						$calculator->updateDeathsModel($mode->type, $mode);
					} catch (InsuficientRegressionDataException) {

					}
				}
			}
		}
	}

	/**
	 *
	 * Recalculate a regression model for player's hits based on the game type
	 *
	 * @param GameModeType      $type
	 * @param AbstractMode|null $mode
	 *
	 * @return numeric[]
	 * @throws InsuficientRegressionDataException
	 * @see RegressionCalculator::calculateRegressionPrediction() To calculate a value from this model
	 */
	public function updateHitsModel(GameModeType $type, ?AbstractMode $mode = null, int $teamCount = 2): array {
		$infoKey = ($this->arena?->id ?? '') . 'hitModel' . $type->value . (isset($mode) && !$mode->rankable ? $mode->id : '') . ($teamCount > 2 ? '-' . $teamCount : '');
		$model = $this->calculateHitRegression($type, $mode, $teamCount);
		try {
			Info::set($infoKey, $model);
		} catch (Exception) {
			// Failed to save the value - ignore
		}
		return $model;
	}

	/**
	 * Recalculate a regression model for player's deaths based on the game type
	 *
	 * @param GameModeType      $type
	 * @param AbstractMode|null $mode
	 *
	 * @return numeric[]
	 * @throws InsuficientRegressionDataException
	 * @see RegressionCalculator::calculateRegressionPrediction() To calculate a value from this model
	 */
	public function updateDeathsModel(GameModeType $type, ?AbstractMode $mode = null, int $teamCount = 2): array {
		$infoKey = ($this->arena?->id ?? '') . 'deathModel' . $type->value . (isset($mode) && !$mode->rankable ? $mode->id : '') . ($teamCount > 2 ? '-' . $teamCount : '');
		$model = $this->calculateDeathRegression($type, $mode, $teamCount);
		try {
			Info::set($infoKey, $model);
		} catch (Exception) {
			// Failed to save the value - ignore
		}
		return $model;
	}

	/**
	 * Recalculate a regression model for player's teammate hits
	 *
	 * @return numeric[]
	 * @throws InsuficientRegressionDataException
	 * @see RegressionCalculator::calculateRegressionPrediction() To calculate a value from this model
	 */
	public function updateHitsOwnModel(?AbstractMode $mode = null, int $teamCount = 2): array {
		$infoKey = ($this->arena?->id ?? '') . 'hitsOwnModel' . (isset($mode) && !$mode->rankable ? $mode->id : '') . ($teamCount > 2 ? '-' . $teamCount : '');
		$model = $this->calculateHitOwnRegression($mode, $teamCount);
		try {
			Info::set($infoKey, $model);
		} catch (Exception) {
			// Failed to save the value - ignore
		}
		return $model;
	}

	/**
	 * Recalculate a regression model for player's teammate deaths
	 *
	 * @return numeric[]
	 * @throws InsuficientRegressionDataException
	 * @see RegressionCalculator::calculateRegressionPrediction() To calculate a value from this model
	 */
	public function updateDeathsOwnModel(?AbstractMode $mode = null, int $teamCount = 2): array {
		$infoKey = ($this->arena?->id ?? '') . 'deathsOwnModel' . (isset($mode) && !$mode->rankable ? $mode->id : '') . ($teamCount > 2 ? '-' . $teamCount : '');
		$model = $this->calculateDeathOwnRegression($mode, $teamCount);
		try {
			Info::set($infoKey, $model);
		} catch (Exception) {
			// Failed to save the value - ignore
		}
		return $model;
	}

	/**
	 * Get a regression model for player's deaths based on the game type
	 *
	 * @param GameModeType      $type
	 * @param AbstractMode|null $mode
	 * @param int               $teamCount
	 *
	 * @return numeric[]
	 * @throws InsuficientRegressionDataException
	 * @see RegressionCalculator::calculateRegressionPrediction() To calculate a value from this model
	 */
	public function getDeathsModel(GameModeType $type, ?AbstractMode $mode = null, int $teamCount = 2): array {
		$infoKey = ((string)($this->arena?->id ?? '')) . 'deathModel' . $type->value . (isset($mode) && !$mode->rankable ? $mode->id : '') . ($teamCount > 2 ? '-' . $teamCount : '');

		/** @var numeric[]|null $model */
		$model = Info::get($infoKey);
		if (empty($model)) {
			$model = $this->calculateDeathRegression($type, $mode, $teamCount);
			try {
				Info::set($infoKey, $model);
			} catch (Exception) {
				// Failed to save the value - ignore
			}
		}
		return $model;
	}

	/**
	 * @param GameModeType      $type
	 * @param AbstractMode|null $mode
	 * @param int               $teamCount
	 *
	 * @return numeric[]
	 * @throws InsuficientRegressionDataException
	 */
	public function calculateDeathRegression(GameModeType $type, ?AbstractMode $mode = null, int $teamCount = 2): array {
		$query = DB::select(
			'mvEvo5RegressionData',
			$type === GameModeType::TEAM ? 'MEDIAN(deaths_other) OVER (PARTITION BY id_game, enemies, teammates) as [value], enemies, teammates, game_length' : 'MEDIAN(deaths) OVER (PARTITION BY id_game, enemies, teammates) as [value], teammates, game_length'
		)
		           ->where('game_type = %s', $type->value)
		           ->groupBy('id_game, enemies, teammates');
		if ($type === GameModeType::TEAM) {
			$query->where('teams = %i', $teamCount);
		}

		$this->filterQueryByMode($mode, $query);

		$data = $query->fetchAll(cache: false);

		if (count($data) < 10) {
			throw new InsuficientRegressionDataException();
		}

		$inputsLinear = [];
		$inputsMultiplication = [];
		$inputsSquared = [];
		$matY = [];
		$actual = [];

		if ($type === GameModeType::TEAM) {
			foreach ($data as $row) {
				$this->prepareDataTeam($row, $inputsLinear, $inputsMultiplication, $inputsSquared, $matY, $actual);
			}
		}
		else {
			foreach ($data as $row) {
				$this->prepareDataSolo($row, $inputsLinear, $inputsMultiplication, $inputsSquared, $matY, $actual);
			}
		}

		return $this->createAndCompareModels($matY, $actual, $inputsLinear, $inputsMultiplication, $inputsSquared);
	}

	/**
	 * @param AbstractMode|null $mode
	 * @param Fluent            $query
	 *
	 * @return void
	 */
	private function filterQueryByMode(?AbstractMode $mode, Fluent $query): void {
		if (isset($mode) && !$mode->rankable) {
			$query->where('id_mode = %i', $mode->id);
		}
		else {
			$query->where('rankable = 1');
		}
		if (isset($this->arena)) {
			$query->where('id_arena = %i', $this->arena->id);
		}
	}

	/**
	 * @param Row         $row
	 * @param numeric[][] $inputsLinear
	 * @param numeric[][] $inputsMultiplication
	 * @param numeric[][] $inputsSquared
	 * @param numeric[][] $matY
	 * @param numeric[]   $actual
	 */
	public function prepareDataTeam(Row $row, array &$inputsLinear, array &$inputsMultiplication, array &$inputsSquared, array &$matY, array &$actual): void {
		$inputsLinear[] = [1, $row->enemies, $row->teammates, $row->game_length];
		$inputsMultiplication[] = [
			1,
			$row->enemies,
			$row->teammates,
			$row->game_length,
			$row->enemies * $row->teammates,
			$row->enemies * $row->game_length,
			$row->teammates * $row->game_length,
		];
		$inputsSquared[] = [
			1,
			$row->enemies,
			$row->teammates,
			$row->game_length,
			$row->enemies * $row->teammates,
			$row->enemies * $row->game_length,
			$row->teammates * $row->game_length,
			$row->enemies ** 2,
			$row->teammates ** 2,
			$row->game_length ** 2,
		];
		$matY[] = [$row->value];
		$actual[] = $row->value;
	}

	/**
	 * @param Row         $row
	 * @param numeric[][] $inputsLinear
	 * @param numeric[][] $inputsMultiplication
	 * @param numeric[][] $inputsSquared
	 * @param numeric[][] $matY
	 * @param numeric[]   $actual
	 */
	public function prepareDataSolo(Row $row, array &$inputsLinear, array &$inputsMultiplication, array &$inputsSquared, array &$matY, array &$actual): void {
		$inputsLinear[] = [1, $row->teammates, $row->game_length];
		$inputsMultiplication[] = [1, $row->teammates, $row->game_length, $row->teammates * $row->game_length,];
		$inputsSquared[] = [
			1,
			$row->teammates,
			$row->game_length,
			$row->teammates * $row->game_length,
			$row->teammates ** 2,
			$row->game_length ** 2,
		];
		$matY[] = [$row->value];
		$actual[] = $row->value;
	}

	/**
	 * @param numeric[][] $matY
	 * @param numeric[]   $actual
	 * @param numeric[][] $inputsLinear
	 * @param numeric[][] $inputsMultiplication
	 * @param numeric[][] $inputsSquared
	 *
	 * @return numeric[]
	 */
	public function createAndCompareModels(array $matY, array $actual, array $inputsLinear, array $inputsMultiplication, array $inputsSquared): array {
		$linearModel = $this->regressionCalculator->regression($inputsLinear, $matY);
		$predictions = $this->regressionCalculator->calculatePredictions($inputsLinear, $linearModel);
		$r2Linear = $this->regressionCalculator->calculateRSquared($predictions, $actual);

		$multiplicationModel = $this->regressionCalculator->regression($inputsMultiplication, $matY);
		$predictions = $this->regressionCalculator->calculatePredictions($inputsMultiplication, $multiplicationModel);
		$r2Multiplication = $this->regressionCalculator->calculateRSquared($predictions, $actual);

		$combinedModel = $this->regressionCalculator->regression($inputsSquared, $matY);
		$predictions = $this->regressionCalculator->calculatePredictions($inputsSquared, $combinedModel);
		$r2Combined = $this->regressionCalculator->calculateRSquared($predictions, $actual);

		// Return the best model
		$maxR2 = max($r2Linear, $r2Combined, $r2Multiplication);
		return match (true) {
			$maxR2 === $r2Linear         => $linearModel,
			$maxR2 === $r2Multiplication => $multiplicationModel,
			default                      => $combinedModel,
		};
	}

	/**
	 * Get a regression model for player's hits based on the game type
	 *
	 * @param GameModeType      $type
	 * @param AbstractMode|null $mode
	 *
	 * @return numeric[]
	 * @throws InsuficientRegressionDataException
	 * @see RegressionCalculator::calculateRegressionPrediction() To calculate a value from this model
	 */
	public function getHitsModel(GameModeType $type, ?AbstractMode $mode = null, int $teamCount = 2): array {
		$infoKey = ($this->arena?->id ?? '') . 'hitModel' . $type->value . (isset($mode) && !$mode->rankable ? $mode->id : '') . ($teamCount > 2 ? '-' . $teamCount : '');

		/** @var numeric[]|null $model */
		$model = Info::get($infoKey);
		if (empty($model)) {
			$model = $this->calculateHitRegression($type, $mode, $teamCount);
			try {
				Info::set($infoKey, $model);
			} catch (Exception) {
				// Failed to save the value - ignore
			}
		}
		return $model;
	}

	/**
	 * @param GameModeType      $type
	 * @param AbstractMode|null $mode
	 *
	 * @return numeric[]
	 * @throws InsuficientRegressionDataException
	 */
	public function calculateHitRegression(GameModeType $type, ?AbstractMode $mode = null, int $teamCount = 2): array {
		$query = DB::select(
			'mvEvo5RegressionData',
			$type === GameModeType::TEAM ? 'MEDIAN(hits_other) OVER (PARTITION BY id_game, enemies, teammates) as [value], enemies, teammates, game_length' : 'MEDIAN(hits) OVER (PARTITION BY id_game, enemies, teammates) as [value], teammates, game_length'
		)
		           ->where('game_type = %s', $type->value)
		           ->groupBy('id_game, enemies, teammates');
		if ($type === GameModeType::TEAM) {
			$query->where('teams = %i', $teamCount);
		}

		$this->filterQueryByMode($mode, $query);

		$data = $query->fetchAll(cache: false);

		if (count($data) < 10) {
			throw new InsuficientRegressionDataException();
		}

		$inputsLinear = [];
		$inputsMultiplication = [];
		$inputsSquared = [];
		$matY = [];
		$actual = [];

		if ($type === GameModeType::TEAM) {
			foreach ($data as $row) {
				$this->prepareDataTeam($row, $inputsLinear, $inputsMultiplication, $inputsSquared, $matY, $actual);
			}
		}
		else {
			foreach ($data as $row) {
				$this->prepareDataSolo($row, $inputsLinear, $inputsMultiplication, $inputsSquared, $matY, $actual);
			}
		}

		return $this->createAndCompareModels($matY, $actual, $inputsLinear, $inputsMultiplication, $inputsSquared);
	}

	/**
	 * Get a regression model for player's teammate hits
	 *
	 * @return numeric[]
	 * @throws InsuficientRegressionDataException
	 * @see RegressionCalculator::calculateRegressionPrediction() To calculate a value from this model
	 */
	public function getHitsOwnModel(?AbstractMode $mode = null, int $teamCount = 2): array {
		$infoKey = ($this->arena?->id ?? '') . 'hitsOwnModel' . (isset($mode) && !$mode->rankable ? $mode->id : '') . ($teamCount > 2 ? '-' . $teamCount : '');

		/** @var numeric[]|null $model */
		$model = Info::get($infoKey);
		if (empty($model)) {
			$model = $this->calculateHitOwnRegression($mode, $teamCount);
			try {
				Info::set($infoKey, $model);
			} catch (Exception) {
				// Failed to save the value - ignore
			}
		}
		return $model;
	}

	/**
	 * @return numeric[]
	 * @throws InsuficientRegressionDataException
	 */
	public function calculateHitOwnRegression(?AbstractMode $mode = null, int $teamCount = 2): array {
		$query = DB::select(
			'mvEvo5RegressionData',
			'MEDIAN(hits_own) OVER (PARTITION BY id_game, enemies, teammates) as [value], enemies, teammates, game_length'
		)
		           ->where('game_type = %s and teams = %i', GameModeType::TEAM->value, $teamCount)
		           ->groupBy('id_game, enemies, teammates');

		$this->filterQueryByMode($mode, $query);

		$data = $query->fetchAll(cache: false);

		if (count($data) < 10) {
			throw new InsuficientRegressionDataException();
		}

		$inputsLinear = [];
		$inputsMultiplication = [];
		$inputsSquared = [];
		$matY = [];
		$actual = [];
		foreach ($data as $row) {
			$this->prepareDataTeam($row, $inputsLinear, $inputsMultiplication, $inputsSquared, $matY, $actual);
		}

		return $this->createAndCompareModels($matY, $actual, $inputsLinear, $inputsMultiplication, $inputsSquared);
	}

	/**
	 * Get a regression mode for player's teammate deaths
	 *
	 * @return numeric[]
	 * @throws InsuficientRegressionDataException
	 * @see RegressionCalculator::calculateRegressionPrediction() To calculate a value from this model
	 */
	public function getDeathsOwnModel(?AbstractMode $mode = null, int $teamCount = 2): array {
		$infoKey = ($this->arena?->id ?? '') . 'deathsOwnModel' . (isset($mode) && !$mode->rankable ? $mode->id : '') . ($teamCount > 2 ? '-' . $teamCount : '');

		/** @var numeric[]|null $model */
		$model = Info::get($infoKey);
		if (empty($model)) {
			$model = $this->calculateDeathOwnRegression($mode, $teamCount);
			try {
				Info::set($infoKey, $model);
			} catch (Exception) {
				// Failed to save the value - ignore
			}
		}
		return $model;
	}

	/**
	 * @return numeric[]
	 * @throws InsuficientRegressionDataException
	 */
	public function calculateDeathOwnRegression(?AbstractMode $mode = null, int $teamCount = 2): array {
		$query = DB::select(
			'mvEvo5RegressionData',
			'MEDIAN(deaths_own) OVER (PARTITION BY id_game, enemies, teammates) as value, enemies, teammates, game_length'
		)->where(
			'game_type = %s AND teams = %i',
			GameModeType::TEAM->value,
			$teamCount
		)->groupBy('id_game, enemies, teammates');

		$this->filterQueryByMode($mode, $query);

		$data = $query->fetchAll(cache: false);

		if (count($data) < 10) {
			throw new InsuficientRegressionDataException();
		}

		$inputsLinear = [];
		$inputsMultiplication = [];
		$inputsSquared = [];
		$matY = [];
		$actual = [];
		foreach ($data as $row) {
			$this->prepareDataTeam($row, $inputsLinear, $inputsMultiplication, $inputsSquared, $matY, $actual);
		}

		return $this->createAndCompareModels($matY, $actual, $inputsLinear, $inputsMultiplication, $inputsSquared);
	}
}
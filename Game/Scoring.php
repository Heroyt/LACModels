<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */
namespace App\GameModels\Game;

use Dibi\Row;
use Lsr\Core\Models\Interfaces\InsertExtendInterface;
use OpenApi\Attributes as OA;

/**
 * Structure containing game's scoring settings
 *
 * Scoring = how many points does a player get for an action.
 *
 * @phpstan-consistent-constructor
 */
#[OA\Schema]
class Scoring implements InsertExtendInterface
{

	public function __construct(
		#[OA\Property]
		public int $deathOther = 0,
		#[OA\Property]
		public int $hitOther = 0,
		#[OA\Property]
		public int $deathOwn = 0,
		#[OA\Property]
		public int $hitOwn = 0,
		#[OA\Property]
		public int $hitPod = 0,
		#[OA\Property]
		public int $shot = 0,
		#[OA\Property]
		public int $machineGun = 0,
		#[OA\Property]
		public int $invisibility = 0,
		#[OA\Property]
		public int $agent = 0,
		#[OA\Property]
		public int $shield = 0,
	) {
	}

	public static function parseRow(Row $row) : static {
		/** @noinspection ProperNullCoalescingOperatorUsageInspection */
		return new static(
			$row->scoring_death_other ?? 0,
			$row->scoring_hit_other ?? 0,
			$row->scoring_death_own ?? 0,
			$row->scoring_hit_own ?? 0,
			$row->scoring_hit_pod ?? 0,
			$row->scoring_shot ?? 0,
			$row->scoring_power_machine_gun ?? 0,
			$row->scoring_power_invisibility ?? 0,
			$row->scoring_power_agent ?? 0,
			$row->scoring_power_shield ?? 0,
		);
	}

	/**
	 * @param array<string,mixed> $data
	 *
	 * @return void
	 */
	public function addQueryData(array &$data) : void {
		$data['scoring_hit_other'] = $this->hitOther;
		$data['scoring_hit_own'] = $this->hitOwn;
		$data['scoring_death_other'] = $this->deathOther;
		$data['scoring_death_own'] = $this->hitOwn;
		$data['scoring_hit_pod'] = $this->hitPod;
		$data['scoring_shot'] = $this->shot;
		$data['scoring_power_machine_gun'] = $this->machineGun;
		$data['scoring_power_invisibility'] = $this->invisibility;
		$data['scoring_power_agent'] = $this->agent;
		$data['scoring_power_shield'] = $this->shield;
	}
}
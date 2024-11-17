<?php

namespace App\GameModels\Game\LaserForce\DataObjects;

use Dibi\Row;
use Lsr\Core\Models\Interfaces\InsertExtendInterface;

class PowerCount implements InsertExtendInterface
{

	public function __construct(
		public int $machineGun = 0,
		public int $invincibility = 0,
		public int $payback = 0,
		public int $nukeStart = 0,
		public int $nuke = 0,
		public int $shield = 0,
		public int $reset = 0,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public static function parseRow(Row $row) : ?static {
		/** @phpstan-ignore return.type */
		return new self(
			$row->machine_gun ?? 0,
			$row->invincibility ?? 0,
			$row->payback ?? 0,
			$row->nuke_start ?? 0,
			$row->nuke ?? 0,
			$row->shield ?? 0,
			$row->reset ?? 0,
		);
	}

	/**
	 * @inheritDoc
	 */
	public function addQueryData(array &$data) : void {
		$data['machine_gun'] = $this->machineGun;
		$data['invincibility'] = $this->invincibility;
		$data['payback'] = $this->payback;
		$data['nuke_start'] = $this->nukeStart;
		$data['nuke'] = $this->nuke;
		$data['shield'] = $this->shield;
		$data['reset'] = $this->reset;
	}
}
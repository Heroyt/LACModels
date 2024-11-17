<?php

namespace App\GameModels\Game\LaserForce\DataObjects;

use Dibi\Row;
use Lsr\Core\Models\Interfaces\InsertExtendInterface;

class LaserBallCounts implements InsertExtendInterface
{

	public function __construct(
		public int $ballGot = 0,
		public int $steals = 0,
		public int $lost = 0,
		public int $passes = 0,
		public int $clears = 0,
		public int $goals = 0,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public static function parseRow(Row $row) : ?static {
		/** @phpstan-ignore return.type */
		return new self(
			$row->ball_got ?? 0,
			$row->steals ?? 0,
			$row->lost ?? 0,
			$row->passes ?? 0,
			$row->clears ?? 0,
			$row->goals ?? 0,
		);
	}

	/**
	 * @inheritDoc
	 */
	public function addQueryData(array &$data) : void {
		$data['ball_got'] = $this->ballGot;
		$data['steals'] = $this->steals;
		$data['lost'] = $this->lost;
		$data['passes'] = $this->passes;
		$data['clears'] = $this->clears;
		$data['goals'] = $this->goals;
	}
}
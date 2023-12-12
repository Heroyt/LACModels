<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */
namespace App\GameModels\Game;

use Dibi\Row;
use Lsr\Core\Models\Interfaces\InsertExtendInterface;
use OpenApi\Attributes as OA;

/**
 * Game's timing settings
 *
 * @phpstan-consistent-constructor
 */
#[OA\Schema]
class Timing implements InsertExtendInterface
{

	/**
	 * @param int $before     Seconds before game
	 * @param int $gameLength Game length in minutes
	 * @param int $after      Seconds after game
	 */
	public function __construct(
		#[OA\Property]
		public int $before = 0,
		#[OA\Property]
		public int $gameLength = 0,
		#[OA\Property]
		public int $after = 0,
	) {
	}

	public static function parseRow(Row $row) : static {
		/** @noinspection ProperNullCoalescingOperatorUsageInspection */
		return new static(
			$row->timing_before ?? 0,
			$row->timing_game_length ?? 0,
			$row->timing_after ?? 0,
		);
	}

	/**
	 * @param array<string,mixed> $data
	 *
	 * @return void
	 */
	public function addQueryData(array &$data) : void {
		$data['timing_before'] = $this->before;
		$data['timing_game_length'] = $this->gameLength;
		$data['timing_after'] = $this->after;
	}
}
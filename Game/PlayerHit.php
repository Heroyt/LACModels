<?php

namespace App\GameModels\Game;

use App\Core\DB;
use Dibi\Exception;
use JsonSerializable;

class PlayerHit implements JsonSerializable
{

	public const TABLE = '';

	public function __construct(
		public Player $playerShot,
		public Player $playerTarget,
		public int    $count = 0,) {
	}

	/**
	 * @return bool
	 */
	public function save() : bool {
		$test = DB::select($this::TABLE, '*')->where('[id_player] = %i AND [id_target] = %i', $this->playerShot->id, $this->playerTarget->id)->fetch();
		$data = [
			'id_player' => $this->playerShot->id,
			'id_target' => $this->playerTarget->id,
			'count'     => $this->count,
		];
		try {
			if (isset($test)) {
				DB::update($this::TABLE, $data, ['[id_player] = %i AND [id_target] = %i', $this->playerShot->id, $this->playerTarget->id]);
			}
			else {
				DB::insert($this::TABLE, $data);
			}
		} catch (Exception $e) {
			return false;
		}
		return true;
	}

	/**
	 * Specify data which should be serialized to JSON
	 *
	 * @link  http://php.net/manual/en/jsonserializable.jsonserialize.php
	 * @return array data which can be serialized by <b>json_encode</b>,
	 * which is a value of any type other than a resource.
	 * @since 5.4.0
	 */
	public function jsonSerialize() : array {
		return [
			'shot'   => $this->playerShot->id_player,
			'target' => $this->playerTarget->id_player,
			'count'  => $this->count,
		];
	}
}
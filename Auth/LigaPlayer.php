<?php

namespace App\GameModels\Auth;

use App\Core\Auth\User;
use App\Core\DB;
use App\Exceptions\ValidationException;
use App\Models\Arena;

/**
 * Same as the regular player, but with the addition of the arena and user parameters
 */
class LigaPlayer extends Player
{

	public const DEFINITION = [
		'user'     => ['class' => User::class],
		'arena'    => ['class' => Arena::class],
		'code'     => ['validators' => [[__CLASS__, 'validateCode']]],
		'nickname' => [],
		// Removed an email because it is saved in a User class
	];

	public User   $user;
	public ?Arena $arena;

	/**
	 * @param string $code
	 * @param Player $player
	 *
	 * @return void
	 * @throws ValidationException
	 */
	public static function validateCode(string $code, Player $player) : void {
		if (!$player->validateUniqueCode($player->getCode())) {
			throw new ValidationException('Invalid player\'s code. Must be unique.');
		}
	}

	public function validateUniqueCode(string $code) : bool {
		// Validate and parse a player's code
		if (!preg_match('/(\d+)-([\da-zA-Z]{5})/', $code, $matches)) {
			$arenaId = isset($this->arena) ? $this->arena->id : 0;
		}
		else {
			$arenaId = (int) $matches[1];
			$code = $matches[2];
		}
		$id = DB::select($this::TABLE, $this::PRIMARY_KEY)->where('%n = %i AND [code] = %s', Arena::PRIMARY_KEY, $arenaId, $code)->fetchSingle();
		return !isset($id) || $id === $this->id;
	}

	public function getCode() : string {
		return (isset($this->arena) ? $this->arena->id : 0).'-'.$this->code;
	}

	public function fetch(bool $refresh = false) : void {
		parent::fetch($refresh);
		$this->email = $this->user->email;
	}

}
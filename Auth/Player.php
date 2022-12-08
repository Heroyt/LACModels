<?php

namespace App\GameModels\Auth;

use App\Core\Info;
use App\GameModels\Auth\Validators\PlayerCode;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Attributes\Validation\Email;
use Lsr\Core\Models\Model;
use Nette\Utils\Random;

#[PrimaryKey('id_user')]
class Player extends Model
{

	public const TABLE = 'players';

	/** @var string Unique code for each player - two players can have the same code if they are from different arenas. */
	#[PlayerCode]
	public string $code;
	public string $nickname;
	#[Email]
	public string $email;

	/**
	 * @param string $code
	 * @param Player $player
	 *
	 * @return void
	 * @throws ValidationException
	 */
	public static function validateCode(string $code, Player $player) : void {
		if (!$player->validateUniqueCode($code)) {
			throw new ValidationException('Invalid player\'s code. Must be unique.');
		}
	}

	/**
	 * Validate the unique player's code to be unique for all player in one arena
	 *
	 * @param string $code
	 *
	 * @return bool
	 */
	public function validateUniqueCode(string $code) : bool {
		$id = DB::select($this::TABLE, $this::getPrimaryKey())->where('[code] = %s', $code)->fetchSingle();
		return !isset($id) || $id === $this->id;
	}

	/**
	 * Generate a random unique code for player
	 *
	 * @return void
	 */
	public function generateRandomCode() : void {
		do {
			$code = Random::generate(5, '0-9A-Z');
		} while (!$this->validateUniqueCode($code));
		$this->code = $code;
	}

	/**
	 * @return string
	 */
	public function getCode() : string {
		return Info::get('arena_id', 0).'-'.$this->code;
	}
}
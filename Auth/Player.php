<?php

namespace App\GameModels\Auth;

use App\Core\AbstractModel;
use App\Core\DB;
use App\Core\Info;
use App\Core\Interfaces\InsertExtendInterface;
use App\Exceptions\ModelNotFoundException;
use App\Exceptions\ValidationException;
use App\Logging\DirectoryCreationException;
use Dibi\Row;
use Nette\Utils\Random;

class Player extends AbstractModel implements InsertExtendInterface
{

	public const TABLE       = 'players';
	public const PRIMARY_KEY = 'id_user';

	public const DEFINITION = [
		'code'     => ['validators' => [[__CLASS__, 'validateCode']]],
		'nickname' => [],
		'email'    => ['validators' => ['email']],
	];

	/** @var string Unique code for each player - two players can have the same code if they are from different arenas. */
	public string $code;
	public string $nickname;
	public string $email;

	/**
	 * @inheritDoc
	 */
	public static function parseRow(Row $row) : ?static {
		if (isset($row->{static::PRIMARY_KEY})) {
			try {
				static::get((int) $row->{static::PRIMARY_KEY});
			} catch (ModelNotFoundException|DirectoryCreationException $e) {
			}
		}
		return null;
	}

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
		$id = DB::select($this::TABLE, $this::PRIMARY_KEY)->where('[code] = %s', $code)->fetchSingle();
		return !isset($id) || $id === $this->id;
	}

	/**
	 * @inheritDoc
	 */
	public function addQueryData(array &$data) : void {
		$data[$this::PRIMARY_KEY] = $this->id;
	}

	/**
	 * Generate a random unique code for player
	 *
	 * @return void
	 */
	public function generateRandomCode() : void {
		do {
			$code = Random::generate(5, '0-9a-zA-Z');
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
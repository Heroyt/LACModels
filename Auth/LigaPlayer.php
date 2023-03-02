<?php

namespace App\GameModels\Auth;

use App\Models\Arena;
use App\Models\Auth\User;
use Lsr\Core\App;
use Lsr\Core\Caching\Cache;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\OneToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Nette\Caching\Cache as CacheParent;

/**
 * Same as the regular player, but with the addition of the arena and user parameters
 */
#[PrimaryKey('id_user')]
class LigaPlayer extends Player
{

	public const CACHE_TAGS = ['liga-players'];

	#[OneToOne]
	public User   $user;
	#[ManyToOne]
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

	/**
	 * @param string $code
	 *
	 * @return bool
	 */
	public function validateUniqueCode(string $code) : bool {
		// Validate and parse a player's code
		if (!preg_match('/(\d+)-([\da-zA-Z]{5})/', $code, $matches)) {
			$arenaId = isset($this->arena) ? $this->arena->id : 0;
		}
		else {
			$arenaId = (int) $matches[1];
			$code = $matches[2];
		}
		$id = DB::select($this::TABLE, $this::getPrimaryKey())->where('%n = %i AND [code] = %s', Arena::getPrimaryKey(), $arenaId, $code)->fetchSingle();
		return !isset($id) || $id === $this->id;
	}

	public function getCode() : string {
		return (isset($this->arena) ? $this->arena->id : 0).'-'.$this->code;
	}

	public function fetch(bool $refresh = false) : void {
		parent::fetch($refresh);
		$this->email = $this->user->email;
	}

	public function jsonSerialize() : array {
		$connections = [];
		try {
			foreach ($this->user->getConnections() as $connection) {
				$connections[] = ['type' => $connection->type->value, 'identifier' => $connection->identifier];
			}
		} catch (ValidationException) {
		}
		return [
			'id'          => $this->id,
			'nickname'    => $this->nickname,
			'code'        => $this->getCode(),
			'arena'       => $this->arena?->id,
			'email'       => $this->email,
			'stats'       => $this->stats,
			'connections' => $connections,
		];
	}

	public function clearCache() : void {
		parent::clearCache();

		// Invalidate cached objects
		/** @var Cache $cache */
		$cache = App::getService('cache');
		$cache->clean([CacheParent::Tags => ['user/'.$this->id.'/games', 'user/'.$this->id.'/stats']]);
	}

}
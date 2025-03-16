<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Traits;

use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\GameModels\Game\Team;
use Lsr\Db\DB;
use Lsr\Helpers\Tools\Timer;
use Lsr\Lg\Results\Interface\Models\PlayerInterface;
use Lsr\Lg\Results\PlayerCollection;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\Orm\Attributes\JsonExclude;
use Lsr\Orm\Attributes\NoDB;
use Lsr\Orm\Attributes\Relations\OneToMany;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Lsr\Orm\Exceptions\ValidationException;
use Lsr\Orm\ModelCollection;
use Throwable;

/**
 * @template P of Player
 */
trait WithPlayers
{
	#[NoDB]
	public int $playerCount {
		get => $this->players->count();
	}
	/** @var class-string<P> */
	#[NoDB, JsonExclude]
	public string $playerClass;
	/** @var PlayerCollection<P> */
	#[OneToMany(class: Player::class, factoryMethod: 'loadPlayers')]
	public PlayerCollection $players;
	/** @var PlayerCollection<P> */
	#[NoDB, JsonExclude]
	public PlayerCollection $playersSorted {
		get {
			/** @phpstan-ignore isset.property */
			if (!isset($this->playersSorted)) {
				/** @var ModelCollection<P> $players */
				$players = $this->players
					->query()
					->sortBy('score')
					->desc()
					->get();
				/** @phpstan-ignore assign.propertyReadOnly */
				$this->playersSorted = new PlayerCollection($players);
			}
			/** @phpstan-ignore return.type, assign.propertyReadOnly */
			return $this->playersSorted;
		}
		/** @phpstan-ignore propertySetHook.parameterType */
		set => $this->playersSorted = $value;
	}

	/**
	 * @return PlayerCollection<P>
	 * @throws Throwable
	 * @throws ModelNotFoundException
	 * @phpstan-ignore method.childReturnType
	 */
	public function loadPlayers(): PlayerCollection {
		/** @var array<int,P> $players */
		$players = [];

		/** @var class-string<P> $className */
		$className = preg_replace(['/(.+)Game$/', '/(.+)Team$/'], '${1}Player', $this::class);
		$primaryKey = $className::getPrimaryKey();
		$gameId = $this instanceof Game ? $this->id : $this->game->id;
		$date = $this instanceof Game ? $this->start?->format('Y-m-d') : $this->game->start?->format('Y-m-d');
		$query = DB::select($className::TABLE, '*')
		           ->where('%n = %i', $this::getPrimaryKey(), $this->id)
		           ->cacheTags(
			           'games/' . $this::SYSTEM . '/' . $gameId,
			           'games/' . $this::SYSTEM . '/' . $gameId . '/players',
			           'games/' . $date,
			           'players',
			           'players/' . $this::SYSTEM
		           );
		if ($this instanceof Team) {
			$query->cacheTags(
				'teams/' . $this::SYSTEM . '/' . $this->id,
				'teams/' . $this::SYSTEM . '/' . $this->id . '/players'
			);
		}
		$rows = $query->fetchAll();
		foreach ($rows as $row) {
			/** @var P $player */
			$player = $className::get($row->$primaryKey, $row);
			if ($this instanceof Game) {
				$player->setGame($this);
			}
			else {
				if ($this instanceof Team) { // @phpstan-ignore-line
					$player->team = $this;
				}
			}
			$players[(int)$player->vest] = $player;
		}
		return new PlayerCollection($players, 'vest');
	}

	/**
	 * @return int
	 * @throws DirectoryCreationException
	 * @throws ModelNotFoundException
	 * @throws Throwable
	 * @throws ValidationException
	 */
	public function getMinScore(): int {
		/** @var Player|null $player */
		$player = $this->players->query()->sortBy('score')->asc()->first();
		if (isset($player)) {
			return $player->score;
		}
		return 0;
	}

	/**
	 * @return int
	 * @throws DirectoryCreationException
	 * @throws ModelNotFoundException
	 * @throws Throwable
	 * @throws ValidationException
	 */
	public function getMaxScore(): int {
		/** @var Player|null $player */
		$player = $this->players->query()->sortBy('score')->desc()->first();
		if (isset($player)) {
			return $player->score;
		}
		return 0;
	}

	/**
	 * @param P ...$players
	 *
	 * @return $this
	 */
	public function addPlayer(PlayerInterface ...$players): static {
		foreach ($players as $player) {
			$this->players->add($player);
		}
		if ($this instanceof Team) {
			foreach ($players as $player) {
				$player->team = $this;
			}
		}
		return $this;
	}

	/**
	 * @return bool
	 * @throws ValidationException
	 */
	public function savePlayers(): bool {
		/** @phpstan-ignore isset.property */
		if (!isset($this->players)) {
			return true;
		}
		Timer::start('game.save.players');
		$players = $this->players->getAll();
		/** @var Player $player */
		// Save players first
		foreach ($players as $player) {
			if (!$player->save()) {
				Timer::stop('game.save.players');
				return false;
			}
		}
		// Save player hits
		Timer::start('game.save.players.hits');
		foreach ($players as $player) {
			if (!$player->saveHits()) {
				Timer::stop('game.save.players');
				Timer::stop('game.save.players.hits');
				return false;
			}
		}
		Timer::stop('game.save.players.hits');
		Timer::stop('game.save.players');
		return true;
	}
}

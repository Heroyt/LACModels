<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Traits;

use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\GameModels\Game\PlayerCollection;
use App\GameModels\Game\Team;
use Dibi\Row;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\Instantiate;
use Lsr\Core\Models\Attributes\NoDB;
use Lsr\Core\Models\Model;

trait WithPlayers
{

	/** @var int */
	#[NoDB]
	public int $playerCount = 0;
	/** @var string */
	#[NoDB]
	public string $playerClass;
	/** @var PlayerCollection */
	#[Instantiate]
	public PlayerCollection $players;
	/** @var PlayerCollection */
	protected PlayerCollection $playersSorted;

	public function __construct(?int $id = null, ?Row $dbRow = null) {
		parent::__construct($id, $dbRow);
		$this->playerCount = count($this->getPlayers());
	}

	/**
	 * @return PlayerCollection
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public function getPlayers() : PlayerCollection {
		if (!isset($this->players)) {
			$this->players = new PlayerCollection();
		}
		if (!empty($this->id) && $this->players->count() === 0) {
			$this->loadPlayers();
		}
		return $this->players;
	}

	/**
	 * @return PlayerCollection
	 * @throws ValidationException
	 * @throws ModelNotFoundException
	 */
	public function loadPlayers() : PlayerCollection {
		if (!isset($this->players)) {
			$this->players = new PlayerCollection();
		}
		/** @var Model|string $className */
		$className = preg_replace(['/(.+)Game$/', '/(.+)Team$/'], '${1}Player', get_class($this));
		$primaryKey = $className::getPrimaryKey();
		$rows = DB::select($className::TABLE, '*')->where('%n = %i', $this::getPrimaryKey(), $this->id)->fetchAll();
		foreach ($rows as $row) {
			/** @var Player $player */
			$player = $className::get($row->$primaryKey, $row);
			if ($this instanceof Game) {
				$player->setGame($this);
			}
			else if ($this instanceof Team) { // @phpstan-ignore-line
				$player->setTeam($this);
			}
			$this->players->set($player, $player->vest);
		}
		return $this->players;
	}

	/**
	 * @return int
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public function getMinScore() : int {
		/** @var Player|null $player */
		$player = $this->getPlayers()->query()->sortBy('score')->asc()->first();
		if (isset($player)) {
			return $player->score;
		}
		return 0;
	}

	/**
	 * @return int
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public function getMaxScore() : int {
		/** @var Player|null $player */
		$player = $this->getPlayers()->query()->sortBy('score')->desc()->first();
		if (isset($player)) {
			return $player->score;
		}
		return 0;
	}

	public function addPlayer(Player ...$players) : static {
		if (!isset($this->players)) {
			$this->players = new PlayerCollection();
		}
		$this->players->add(...$players);
		if ($this instanceof Team) {
			foreach ($players as $player) {
				$player->setTeam($this);
			}
		}
		return $this;
	}

	/**
	 * @return PlayerCollection
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public function getPlayersSorted() : PlayerCollection {
		if (!isset($this->playersSorted)) {
			/* @phpstan-ignore-next-line */
			$this->playersSorted = $this
				->getPlayers()
				->query()
				->sortBy('score')
				->desc()
				->get();
		}
		/* @phpstan-ignore-next-line */
		return $this->playersSorted;
	}

	/**
	 * @return bool
	 * @throws ValidationException
	 */
	public function savePlayers() : bool {
		if (!isset($this->players)) {
			return true;
		}
		Timer::start('game.save.players');
		/** @var Player $player */
		// Save players first
		foreach ($this->players as $player) {
			if (!$player->save()) {
				Timer::stop('game.save.players');
				return false;
			}
		}
		// Save player hits
		Timer::start('game.save.players.hits');
		foreach ($this->players as $player) {
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
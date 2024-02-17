<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Traits;

use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\GameModels\Game\PlayerCollection;
use App\GameModels\Game\Team;
use InvalidArgumentException;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\Instantiate;
use Lsr\Core\Models\Attributes\NoDB;
use Lsr\Core\Models\Attributes\OneToMany;
use Lsr\Core\Models\LoadingType;
use Lsr\Core\Models\Model;
use Lsr\Helpers\Tools\Timer;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Throwable;

/**
 * @template P of Player
 */
trait WithPlayers
{

	/** @var int */
	#[NoDB]
	public int $playerCount = 0;
	/** @var class-string<P> */
	#[NoDB]
	public string $playerClass;
	/** @var PlayerCollection<P> */
	#[Instantiate, OneToMany(class: Player::class, loadingType: LoadingType::LAZY)]
	public PlayerCollection $players;
	/** @var PlayerCollection<P> */
	protected PlayerCollection $playersSorted;

	/**
	 * @return PlayerCollection<P>
	 */
	public function getPlayers() : PlayerCollection {
		if (!isset($this->players)) {
			$this->players = new PlayerCollection();
		}
		if (!empty($this->id) && $this->players->count() === 0) {
			try {
				$this->loadPlayers();
			} catch (Throwable $e) {
				// Do nothing
			}
		}
		return $this->players;
	}

	/**
	 * @return PlayerCollection<P>
	 * @throws DirectoryCreationException
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 * @throws Throwable
	 */
	public function loadPlayers() : PlayerCollection {
		if (!isset($this->players)) {
			$this->players = new PlayerCollection();
		}
		/** @var Model|string $className */
		$className = preg_replace(['/(.+)Game$/', '/(.+)Team$/'], '${1}Player', get_class($this));
		$primaryKey = $className::getPrimaryKey();
		$gameId = $this instanceof Game ? $this->id : $this->getGame()->id;
		$date = $this instanceof Game ? $this->start?->format('Y-m-d') : $this->getGame()->start?->format('Y-m-d');
		$query = DB::select($className::TABLE, '*')
							 ->where('%n = %i', $this::getPrimaryKey(), $this->id)
							 ->cacheTags('games/'.$this::SYSTEM.'/'.$gameId, 'games/'.$this::SYSTEM.'/'.$gameId.'/players', 'games/'.$date, 'players', 'players/'.$this::SYSTEM);
		if ($this instanceof Team) {
			$query->cacheTags('teams/'.$this::SYSTEM.'/'.$this->id, 'teams/'.$this::SYSTEM.'/'.$this->id.'/players');
		}
		$rows = $query->fetchAll();
		foreach ($rows as $row) {
			/** @var Player $player */
			$player = $className::get($row->$primaryKey, $row);
			if ($this instanceof Game) {
				$player->setGame($this);
			}
			else if ($this instanceof Team) { // @phpstan-ignore-line
				$player->setTeam($this);
			}
			try {
				$this->players->set($player, $player->vest);
			} catch (InvalidArgumentException) {

			}
		}
		return $this->players;
	}

	/**
	 * @return int
	 * @throws DirectoryCreationException
	 * @throws ModelNotFoundException
	 * @throws Throwable
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
	 * @throws DirectoryCreationException
	 * @throws ModelNotFoundException
	 * @throws Throwable
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

	/**
	 * @param P ...$players
	 *
	 * @return $this
	 */
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
	 * @return PlayerCollection<P>|Player[]
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

	/**
	 * @return int
	 */
	public function getPlayerCount() : int {
		if (!isset($this->playerCount) || $this->playerCount < 1) {
			$this->playerCount = $this->getPlayers()->count();
		}
		return $this->playerCount;
	}
}
<?php

namespace App\GameModels\Traits;

use App\GameModels\Game\Game;
use App\GameModels\Game\Team;
use App\GameModels\Game\TeamCollection;
use InvalidArgumentException;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\Instantiate;
use Lsr\Core\Models\Attributes\NoDB;
use Lsr\Helpers\Tools\Timer;

/**
 * @template T of Team
 */
trait WithTeams
{

	/** @var class-string<T> */
	#[NoDB]
	public string $teamClass;

	/** @var TeamCollection<T> */
	#[Instantiate]
	public TeamCollection $teams;
	/** @var TeamCollection<T> */
	protected TeamCollection $teamsSorted;

	/**
	 * @param T ...$teams
	 *
	 * @return $this
	 */
	public function addTeam(Team ...$teams) : static {
		if (!isset($this->teams)) {
			$this->teams = new TeamCollection();
		}
		$this->teams->add(...$teams);
		return $this;
	}

	/**
	 * @return TeamCollection<T>
	 */
	public function getTeamsSorted() : TeamCollection {
		if (empty($this->teamsSorted)) {
			/* @phpstan-ignore-next-line */
			$this->teamsSorted = $this
				->getTeams()
				->query()
				->sortBy('score')
				->desc()
				->get();
		}
		/* @phpstan-ignore-next-line */
		return $this->teamsSorted;
	}

	/**
	 * @return TeamCollection<T>
	 */
	public function getTeams() : TeamCollection {
		if (!isset($this->teams)) {
			$this->teams = new TeamCollection();
		}
		if ($this->teams->count() === 0) {
			$this->loadTeams();
		}
		return $this->teams;
	}

	/**
	 * @return TeamCollection<T>
	 */
	public function loadTeams() : TeamCollection {
		if (!isset($this->teams)) {
			$this->teams = new TeamCollection();
		}
		/** @var class-string<Game> $className */
		$className = preg_replace('/(.+)Game$/', '${1}Team', get_class($this));
		$primaryKey = $className::getPrimaryKey();
		$rows = DB::select($className::TABLE, '*')
							->where('%n = %i', $this::getPrimaryKey(), $this->id)
							->cacheTags('games/'.$this::SYSTEM.'/'.$this->id, 'games/'.$this::SYSTEM.'/'.$this->id.'/teams', 'games/'.$this->start?->format('Y-m-d'), 'teams', 'teams/'.$this::SYSTEM)
							->fetchAll();
		foreach ($rows as $row) {
			/** @var Team $team */
			$team = new $className($row->$primaryKey, $row);
			/* @phpstan-ignore-next-line */
			if ($this instanceof Game) {
				$team->setGame($this);
			}
			try {
				$this->teams->set($team, $team->color);
			} catch (InvalidArgumentException) {

			}
		}
		return $this->teams;
	}

	/**
	 * Save all teams to the DB
	 *
	 * @return bool
	 * @throws ValidationException
	 */
	public function saveTeams() : bool {
		Timer::start('game.save.teams');
		if (!isset($this->teams)) {
			Timer::stop('game.save.teams');
			return true;
		}
		foreach ($this->teams as $team) {
			if (!$team->save()) {
				Timer::stop('game.save.teams');
				return false;
			}
		}
		Timer::stop('game.save.teams');
		return true;
	}
}
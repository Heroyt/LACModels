<?php

namespace App\GameModels\Traits;

use App\GameModels\Game\Game;
use App\GameModels\Game\Team;
use App\GameModels\Game\TeamCollection;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\Instantiate;
use Lsr\Core\Models\Attributes\NoDB;

trait WithTeams
{

	/** @var class-string<Team> */
	#[NoDB]
	public string $teamClass;

	/** @var TeamCollection */
	#[Instantiate]
	public TeamCollection $teams;
	/** @var TeamCollection */
	protected TeamCollection $teamsSorted;

	public function addTeam(Team ...$teams) : static {
		if (!isset($this->teams)) {
			$this->teams = new TeamCollection();
		}
		$this->teams->add(...$teams);
		return $this;
	}

	/**
	 * @return TeamCollection
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
	 * @return TeamCollection
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

	public function loadTeams() : TeamCollection {
		if (!isset($this->teams)) {
			$this->teams = new TeamCollection();
		}
		/** @var class-string<Game> $className */
		$className = preg_replace('/(.+)Game$/', '${1}Team', get_class($this));
		$primaryKey = $className::getPrimaryKey();
		$rows = DB::select($className::TABLE, '*')->where('%n = %i', $this::getPrimaryKey(), $this->id)->fetchAll();
		foreach ($rows as $row) {
			/** @var Team $team */
			$team = new $className($row->$primaryKey, $row);
			/* @phpstan-ignore-next-line */
			if ($this instanceof Game) {
				$team->setGame($this);
			}
			$this->teams->set($team, $team->color);
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
		if (!isset($this->teams)) {
			return true;
		}
		foreach ($this->teams as $team) {
			if (!$team->save()) {
				return false;
			}
		}
		return true;
	}
}
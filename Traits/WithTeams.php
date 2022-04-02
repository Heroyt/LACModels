<?php

namespace App\GameModels\Traits;

use App\Core\DB;
use App\Exceptions\ValidationException;
use App\GameModels\Game\Game;
use App\GameModels\Game\Team;
use App\GameModels\Game\TeamCollection;
use App\Services\Timer;

trait WithTeams
{

	/** @var Team */
	public string $teamClass;

	/** @var TeamCollection|Team[] */
	public TeamCollection $teams;
	/** @var TeamCollection|Team[] */
	protected TeamCollection $teamsSorted;

	public function addTeam(Team ...$teams) : static {
		if (!isset($this->teams)) {
			$this->teams = new TeamCollection();
		}
		$this->teams->add(...$teams);
		return $this;
	}

	/**
	 * @return TeamCollection|Team[]
	 */
	public function getTeamsSorted() : TeamCollection {
		if (empty($this->teamsSorted)) {
			/** @noinspection PhpFieldAssignmentTypeMismatchInspection */
			$this->teamsSorted = $this
				->getTeams()
				->query()
				->sortBy('score')
				->desc()
				->get();
		}
		return $this->teamsSorted;
	}

	/**
	 * @return TeamCollection|Team[]
	 */
	public function getTeams() : TeamCollection {
		if (!isset($this->teams)) {
			$this->loadTeams();
		}
		return $this->teams;
	}

	public function loadTeams() : TeamCollection {
		if (!isset($this->teams)) {
			$this->teams = new TeamCollection();
		}
		$className = preg_replace('/(.+)Game$/', '${1}Team', get_class($this));
		$primaryKey = $className::PRIMARY_KEY;
		$rows = DB::select($className::TABLE, '*')->where('%n = %i', $this::PRIMARY_KEY, $this->id)->fetchAll();
		foreach ($rows as $row) {
			/** @var Team $team */
			$team = new $className($row->$primaryKey, $row);
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
<?php

namespace App\GameModels\Traits;

use App\GameModels\Game\Game;
use App\GameModels\Game\Team;
use App\GameModels\Game\TeamCollection;
use InvalidArgumentException;
use Lsr\Db\DB;
use Lsr\Helpers\Tools\Timer;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\Orm\Attributes\JsonExclude;
use Lsr\Orm\Attributes\NoDB;
use Lsr\Orm\Attributes\Relations\OneToMany;

/**
 * @template T of Team
 */
trait WithTeams
{
    #[NoDB]
    public int $teamCount {
        get {
            if (!isset($this->teamCount) || $this->teamCount < 1) {
                $this->teamCount = $this->teams->count();
            }
            return $this->teamCount;
        }
    }

    /** @var class-string<T> */
    #[NoDB, JsonExclude]
    public string $teamClass;

    /** @var TeamCollection<T> */
    #[OneToMany(class: Team::class, factoryMethod: 'loadTeams')]
    public TeamCollection $teams;
    /** @var TeamCollection<T> */
    #[NoDB, JsonExclude]
    public TeamCollection $teamsSorted {
        get {
            if (empty($this->teamsSorted)) {
                $this->teamsSorted = new TeamCollection(
                  $this
                    ->teams
                    ->query()
                    ->sortBy('score')
                    ->desc()
                    ->get()
                );
            }
            return $this->teamsSorted;
        }
    }

    /**
     * @param  T  ...$teams
     *
     * @return $this
     */
    public function addTeam(Team ...$teams) : static {
        foreach ($teams as $team) {
            $this->teams->add($team);
        }
        return $this;
    }

    /**
     * @return TeamCollection<T>
     */
    public function loadTeams() : TeamCollection {
        $teams = [];
        /** @var class-string<Game> $className */
        $className = preg_replace('/(.+)Game$/', '${1}Team', get_class($this));
        $primaryKey = $className::getPrimaryKey();
        $rows = DB::select($className::TABLE, '*')
          ->where('%n = %i', $this::getPrimaryKey(), $this->id)
          ->cacheTags(
            'games/'.$this::SYSTEM.'/'.$this->id,
            'games/'.$this::SYSTEM.'/'.$this->id.'/teams',
            'games/'.$this->start?->format('Y-m-d'),
            'teams',
            'teams/'.$this::SYSTEM
          )
          ->fetchAll();
        foreach ($rows as $row) {
            /** @var Team $team */
            $team = new $className($row->$primaryKey, $row);
            /* @phpstan-ignore-next-line */
            if ($this instanceof Game) {
                $team->setGame($this);
            }
            try {
                $teams[$team->color] = $team;
            } catch (InvalidArgumentException) {

            }
        }
        return new TeamCollection($teams, 'color');
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

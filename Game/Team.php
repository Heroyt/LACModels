<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game;

use App\GameModels\Factory\TeamFactory;
use App\GameModels\Traits\WithGame;
use App\GameModels\Traits\WithPlayers;
use App\Models\BaseModel;
use App\Models\DataObjects\Import\TeamImportDto;
use App\Models\Tournament\Team as TournamentTeam;
use Dibi\Row;
use Lsr\Db\DB;
use Lsr\Lg\Results\Interface\Models\TeamInterface;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\ObjectValidation\Attributes\Required;
use Lsr\ObjectValidation\Attributes\StringLength;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\PrimaryKey;
use Throwable;

/**
 * Base class for Team models
 *
 * @template P of Player
 * @template G of Game
 *
 * @use WithPlayers<P>
 * @use WithGame<G>
 */
#[PrimaryKey('id_team')]
#[Factory(TeamFactory::class)] // @phpstan-ignore-line
abstract class Team extends BaseModel implements TeamInterface
{
    /** @phpstan-use WithPlayers<P> */
    use WithPlayers;

	/** @phpstan-use WithGame<G> */
	use WithGame;

	public const string PRIMARY_KEY = 'id_team';
	public const        SYSTEM      = '';

    public const string DI_TAG            = 'teamDataExtension';
	const array         IMPORT_PROPERTIES = [
		'color',
		'score',
		'bonus',
		'position',
		'name',
	];

    #[Required]
    public int $color = 0;
    #[Required]
    public int $score = 0;
    public ?int   $bonus    = null;
    #[Required]
    public int $position = 0;
    #[Required]
    #[StringLength(min: 1, max: 99)]
    public string $name = '';

	#[ManyToOne('id_team', 'id_tournament_team')]
	public ?TournamentTeam $tournamentTeam = null;


    public function __construct(?int $id = null, ?Row $dbRow = null) {
        $this->cacheTags[] = 'games/'.$this::SYSTEM;
        $this->cacheTags[] = 'teams/'.$this::SYSTEM;
        parent::__construct($id, $dbRow);
        $this->playerCount = $this->players->count();
	}

	public static function fromImportDto(TeamImportDto $data): static {
		/** @phpstan-ignore-next-line */
		$team = new static();
		foreach (static::IMPORT_PROPERTIES as $property) {
			if (isset($data->{$property})) {
				$team->{$property} = $data->{$property};
			}
		}
		if (isset($data->tournamentTeam) && $data->tournamentTeam > 0) {
			try {
				$team->tournamentTeam = TournamentTeam::get($data->tournamentTeam);
			} catch (ModelNotFoundException) {
			}
		}
		return $team;
    }

    public function save() : bool {
        try {
            /** @var int|null $test */
            $test = DB::select($this::TABLE, $this::getPrimaryKey())->where(
              'id_game = %i && name = %s',
              $this->game->id,
              $this->name
            )->fetchSingle(cache: false);
            if (isset($test)) {
                $this->id = $test;
            }
        } catch (Throwable) {
        }

        if (isset($this->tournamentTeam)) {
			$this->tournamentTeam->save();
		}

		return parent::save();
    }

    /**
     * @return int
     */
    public function getDeaths() : int {
        $sum = 0;
        foreach ($this->players as $player) {
            $sum += $player->deaths;
        }
        return $sum;
    }

    /**
	 * @return int
	 */
	public function getDeathsOwn(): int {
		$sum = 0;
		foreach ($this->getPlayers() as $player) {
			$sum += $player->deathsOwn;
		}
		return $sum;
	}

	/**
	 * Get the average skill of the players in the team.
	 *
	 * @return float The average skill of the team.
	 */
	public function getSkill(): float {
		$sum = 0;
		foreach ($this->getPlayers() as $player) {
			$sum += $player->getSkill();
		}
		return $this->getPlayerCount() === 0 ? $sum : $sum / $this->getPlayerCount();
	}

	/**
     * @return float
     */
    public function getAccuracy() : float {
        return $this->getShots() === 0 ? 0 : round(100 * $this->getHits() / $this->getShots(), 2);
    }

    /**
     * @return int
     */
    public function getShots() : int {
        $sum = 0;
        foreach ($this->players as $player) {
            $sum += $player->shots;
        }
        return $sum;
    }

    /**
     * @return int
     */
    public function getHits() : int {
        $sum = 0;
        foreach ($this->players as $player) {
            $sum += $player->hits;
        }
        return $sum;
    }

    /**
	 * @return int
	 */
	public function getHitsOwn(): int {
		$sum = 0;
		foreach ($this->getPlayers() as $player) {
			$sum += $player->hitsOwn;
		}
		return $sum;
	}

	/**
     * @param  Team  $team
     *
     * @return int
     * @throws ValidationException
     * @throws DirectoryCreationException
     */
    public function getHitsTeam(Team $team) : int {
        $sum = 0;
        foreach ($this->players as $player) {
            foreach ($player->getHitsPlayers() as $hits) {
                if ($hits->playerTarget->team?->color === $team->color) {
                    $sum += $hits->count;
                }
            }
        }
        return $sum;
    }

    /**
     * @param  bool  $includeSystem
     *
     * @return string
     * @throws Throwable
     */
    public function getTeamBgClass(bool $includeSystem = false) : string {
        return 'team-'.($includeSystem ? ($this->game::SYSTEM).'-' : '').$this->color;
    }

    /**
     * @return int
     */
    public function getTeamColor() : int {
        return $this->color;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize() : array {
        $data = parent::jsonSerialize();
        if (isset($data['data'])) {
            unset($data['data']);
        }
        if (isset($data['hooks'])) {
            unset($data['hooks']);
        }
        if (isset($data['players'])) {
            unset($data['players']);
        }
        return $data;
    }

    public function fillFromRow(): void {
		if (!isset($this->row)) {
			return;
		}
		parent::fillFromRow();
	}

	/**
	 * @param int $bonus
	 *
	 * @return static
	 * @throws Throwable
	 */
    public function setBonus(int $bonus) : static {
        $this->bonus = $bonus;
        if (isset($this->getGame()->tournamentGame, $this->tournamentTeam)) {
			foreach ($this->getGame()->tournamentGame->teams as $tournamentTeam) {
				if ($tournamentTeam->team->id !== $this->tournamentTeam->id) {
					continue;
				}
				$tournamentTeam->score = $this->getScore();
			}
		}
        return $this;
    }

    /**
     * @return int
     */
    public function getScore() : int {
        $score = $this->score;
        if (isset($this->bonus)) {
            $score += $this->bonus;
        }
        return $score;
    }

}

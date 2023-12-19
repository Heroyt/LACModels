<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game;

use App\GameModels\Factory\TeamFactory;
use App\GameModels\Traits\WithGame;
use App\GameModels\Traits\WithPlayers;
use Dibi\Row;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\Factory;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Attributes\Validation\Required;
use Lsr\Core\Models\Attributes\Validation\StringLength;
use Lsr\Core\Models\Model;
use Lsr\Logging\Exceptions\DirectoryCreationException;
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
abstract class Team extends Model
{
	/** @phpstan-use WithPlayers<P> */
	use WithPlayers;

	/** @phpstan-use WithGame<G> */
	use WithGame;

	public const PRIMARY_KEY = 'id_team';
	public const SYSTEM      = '';

	#[Required]
	public int $color;
	#[Required]
	public int $score;
	public ?int $bonus = null;
	#[Required]
	public int $position;
	#[Required]
	#[StringLength(1, 99)]
	public string $name;

	#[ManyToOne('id_team', 'id_tournament_team')]
	public ?\App\Models\Tournament\Team $tournamentTeam = null;


	public function __construct(?int $id = null, ?Row $dbRow = null) {
		$this->cacheTags[] = 'games/' . $this::SYSTEM;
		$this->cacheTags[] = 'teams/' . $this::SYSTEM;
		parent::__construct($id, $dbRow);
		$this->playerCount = $this->getPlayers()->count();
	}

	public function save(): bool {
		try {
			/** @var int|null $test */
			$test = DB::select($this::TABLE, $this::getPrimaryKey())->where('id_game = %i && name = %s', $this->getGame()->id, $this->name)->fetchSingle(cache: false);
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
		foreach ($this->getPlayers() as $player) {
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
		foreach ($this->getPlayers() as $player) {
			$sum += $player->shots;
		}
		return $sum;
	}

	/**
	 * @return int
	 */
	public function getHits() : int {
		$sum = 0;
		foreach ($this->getPlayers() as $player) {
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
	 * @param Team $team
	 *
	 * @return int
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 * @throws DirectoryCreationException
	 */
	public function getHitsTeam(Team $team) : int {
		$sum = 0;
		foreach ($this->getPlayers() as $player) {
			foreach ($player->getHitsPlayers() as $hits) {
				if ($hits->playerTarget->getTeam()?->color === $team->color) {
					$sum += $hits->count;
				}
			}
		}
		return $sum;
	}

	/**
	 * @param bool $includeSystem
	 *
	 * @return string
	 * @throws Throwable
	 */
	public function getTeamBgClass(bool $includeSystem = false) : string {
		return 'team-'.($includeSystem ? $this->getGame()::SYSTEM.'-' : '').$this->color;
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
		if (isset($data['players'])) {
			unset($data['players']);
		}
		if (isset($data['game'])) {
			unset($data['game']);
		}
		return $data;
	}

	/**
	 * @return int
	 */
	public function getScore(): int {
		$score = $this->score;
		if (isset($this->bonus)) {
			$score += $this->bonus;
		}
		return $score;
	}

	/**
	 * @param int $bonus
	 * @return static
	 */
	public function setBonus(int $bonus): static {
		$this->bonus = $bonus;
		if (isset($this->game->tournamentGame, $this->tournamentTeam)) {
			foreach ($this->game->tournamentGame->teams as $tournamentTeam) {
				if ($tournamentTeam->team->id !== $this->tournamentTeam->id) {
					continue;
				}
				$tournamentTeam->score = $this->getScore();
			}
		}
		return $this;
	}

}
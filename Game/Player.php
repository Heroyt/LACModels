<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game;

use App\GameModels\Factory\PlayerFactory;
use App\GameModels\Traits\WithGame;
use App\Models\Auth\Player as User;
use Dibi\Exception;
use Lsr\Core\App;
use Lsr\Core\Caching\Cache;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\Factory;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\NoDB;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Attributes\Validation\Required;
use Lsr\Core\Models\Attributes\Validation\StringLength;
use Lsr\Core\Models\Model;
use Lsr\Logging\Exceptions\DirectoryCreationException;

/**
 * Base class for player models
 */
#[PrimaryKey('id_player')]
#[Factory(PlayerFactory::class)] // @phpstan-ignore-line
abstract class Player extends Model
{
	use WithGame;

	public const CLASSIC_BESTS = ['score', 'hits', 'score', 'accuracy', 'shots', 'miss'];

	#[Required]
	#[StringLength(1, 15)]
	public string $name     = '';
	public int    $score    = 0;
	public int    $skill    = 0;
	public int    $vest     = 0;
	public int    $shots    = 0;
	public int    $accuracy = 0;
	public int    $hits     = 0;
	public int    $deaths   = 0;
	public int    $position = 0;

	/** @var PlayerHit[] */
	#[NoDB]
	public array $hitPlayers = [];

	#[NoDB]
	public int $teamNum = 0;

	#[ManyToOne(foreignKey: 'id_team')]
	public ?Team           $team              = null;
	#[ManyToOne]
	public ?User           $user              = null;
	protected int          $color             = 0;
	protected ?Player      $favouriteTarget   = null;
	protected ?Player      $favouriteTargetOf = null;
	protected PlayerTrophy $trophy;

	/**
	 * @return bool
	 * @throws ValidationException
	 */
	public function save() : bool {
		/** @var int|null $test */
		$test = DB::select($this::TABLE, $this::getPrimaryKey())->where('id_game = %i && name = %s && vest = %i', $this->game?->id, $this->name, $this->vest)->fetchSingle();
		if (isset($test)) {
			$this->id = $test;
		}
		//$this->calculateSkill();
		return parent::save();
	}

	/**
	 * Calculate a skill level based on the player's results
	 *
	 * The skill value aims to better evaluate the player's play style than the regular score value.
	 * It should take multiple metrics into account.
	 * Other LG system implementations should modify this function to calculate the value based on its specific metrics.
	 * The value must be normalized based on the game's length.
	 *
	 * @pre  The player's results should be set.
	 * @post The Player::$skill property is set to the calculated value
	 *
	 * @return int A whole number evaluation on an arbitrary scale (no max or min value).
	 */
	public function calculateSkill() : int {
		$this->skill = (int) round($this->calculateBaseSkill());

		return $this->skill;
	}

	/**
	 * Calculate the base, not rounded skill level
	 *
	 * @return float
	 */
	protected function calculateBaseSkill() : float {
		$skill = 0.0;

		// Add points for each hit
		$skill += $this->hits * 4;

		// Add points for K:D -
		$kd = $this->getKd();
		if ($kd >= 1) {
			$skill += $kd * 10;
		}
		else if ($kd !== 0.0) {
			$skill -= (1 / $kd) * 5;
		}

		// Add points for deviation from an average K:D
		$averageKd = $this->getGame()->getAverageKd();
		$kdDiff = $kd - $averageKd;
		$skill += $kdDiff * 2;

		// Add points for accuracy
		$skill += 5 * ($this->accuracy / 100);

		// Normalize based on the game's length
		$gameLength = $this->getGame()->getRealGameLength();
		if ($gameLength !== 0.0) {
			$skill *= 15 / $gameLength;
		}
		return $skill;
	}

	public function getKd() : float {
		return $this->hits / ($this->deaths === 0 ? 1 : $this->deaths);
	}

	public function instantiateProperties() : void {
		parent::instantiateProperties();

		// Set the teamNum and color property
		$this->teamNum = $this->getTeamColor();
	}

	/**
	 * @return int
	 */
	public function getTeamColor() : int {
		if (empty($this->color)) {
			$this->color = (isset($this->game) && $this->getGame()->mode?->isSolo() ? 2 : $this->getTeam()?->color) ?? 2;
		}
		return $this->color;
	}

	/**
	 * @return Team|null
	 */
	public function getTeam() : ?Team {
		return $this->team;
	}

	/**
	 * @param Team $team
	 *
	 * @return Player
	 */
	public function setTeam(Team $team) : Player {
		$this->team = $team;
		$this->color = $this->team->color;
		//$team->getPlayers()->add($this);
		return $this;
	}

	/**
	 * @return bool
	 */
	public function saveHits() : bool {
		if (empty($this->hitPlayers)) {
			return true;
		}
		$table = '';
		$values = [];
		foreach ($this->hitPlayers as $hits) {
			$table = $hits::TABLE;
			$values[] = $hits->getQueryData();
		}
		try {
			/** @phpstan-ignore-next-line */
			return DB::replace($table, $values) > 0;
		} catch (Exception) {
			return false;
		}
	}

	/**
	 * Get a players position in today's leaderboard
	 *
	 * @param string $property
	 *
	 * @return int
	 */
	public function getTodayPosition(string $property) : int {
		return 0; // TODO: Implement
	}

	/**
	 * Get missed shots
	 *
	 * @return int
	 */
	public function getMiss() : int {
		return $this->shots - $this->hits;
	}

	/**
	 * Get one trophy
	 *
	 * @return array{name:string,icon:string}
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public function getBestAt() : array {
		if (!isset($this->trophy)) {
			$this->trophy = new PlayerTrophy($this);
		}
		return $this->trophy->getOne();
	}

	/**
	 * Get all trophies
	 *
	 * @return array{name:string,icon:string}[]
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public function getAllBestAt() : array {
		if (!isset($this->trophy)) {
			$this->trophy = new PlayerTrophy($this);
		}
		return $this->trophy->getAll();
	}

	/**
	 * Get a player that this player hit the most
	 *
	 * @return Player|null
	 * @throws DirectoryCreationException
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public function getFavouriteTarget() : ?Player {
		if (!isset($this->favouriteTarget)) {
			$max = 0;
			foreach ($this->getHitsPlayers() as $hits) {
				if ($hits->count > $max) {
					$this->favouriteTarget = $hits->playerTarget;
					$max = $hits->count;
				}
			}
		}
		return $this->favouriteTarget;
	}

	/**
	 * @return PlayerHit[]
	 * @throws DirectoryCreationException
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public function getHitsPlayers() : array {
		if (empty($this->hitPlayers)) {
			return $this->loadHits();
		}
		return $this->hitPlayers;
	}

	/**
	 * @return PlayerHit[]
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 * @throws DirectoryCreationException
	 */
	public function loadHits() : array {
		/** @var PlayerHit $className */
		$className = str_replace('Player', 'PlayerHit', get_class($this));
		$hits = DB::select($className::TABLE, 'id_target, count')->where('id_player = %i', $this->id)->fetchAll();
		foreach ($hits as $row) {
			$this->addHits($this::get($row->id_target), $row->count);
		}
		return $this->hitPlayers;
	}

	/**
	 * @param Player $player
	 * @param int    $count
	 *
	 * @return $this
	 */
	public function addHits(Player $player, int $count) : Player {
		/** @var PlayerHit $className */
		$className = str_replace('Player', 'PlayerHit', get_class($this));
		$this->hitPlayers[$player->vest] = new $className($this, $player, $count);
		return $this;
	}

	/**
	 * Get a player that hit this player the most
	 *
	 * @return Player|null
	 * @throws DirectoryCreationException
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public function getFavouriteTargetOf() : ?Player {
		if (!isset($this->favouriteTargetOf)) {
			$max = 0;
			/** @var static $player */
			foreach ($this->getGame()->getPlayers() as $player) {
				if ($player->id === $this->id) {
					continue;
				}
				$hits = $player->getHitsPlayer($this);
				if ($hits > $max) {
					$max = $hits;
					$this->favouriteTargetOf = $player;
				}
			}
		}
		return $this->favouriteTargetOf;
	}

	/**
	 * @param Player $player
	 *
	 * @return int
	 * @throws DirectoryCreationException
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public function getHitsPlayer(Player $player) : int {
		return $this->getHitsPlayers()[$player->vest]->count ?? 0;
	}

	/**
	 * @return array<string, mixed>
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 * @throws DirectoryCreationException
	 */
	public function jsonSerialize() : array {
		$data = parent::jsonSerialize();
		$data['team'] = $this->getTeam()?->id;
		if (isset($data['game'])) {
			unset($data['game']);
		}
		$data['hitPlayers'] = $this->getHitsPlayers();
		return $data;
	}

	public function update() : bool {
		// Invalidate cache
		/** @var Cache $cache */
		$cache = App::getService('cache');
		$cache->remove('players/'.$this->getGame()::SYSTEM.'/'.$this->id);
		return parent::update();
	}

	public function delete() : bool {
		// Invalidate cache
		/** @var Cache $cache */
		$cache = App::getService('cache');
		$cache->remove('players/'.$this->getGame()::SYSTEM.'/'.$this->id);
		return parent::delete();
	}

	/**
	 * @return int
	 */
	public function getColor() : int {
		return $this->color;
	}

}
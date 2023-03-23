<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game;

use App\GameModels\Factory\PlayerFactory;
use App\GameModels\Traits\WithGame;
use App\Models\Auth\Player as User;
use Dibi\Exception;
use Dibi\Row;
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
use Lsr\Helpers\Tools\Strings;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Throwable;

/**
 * Base class for player models
 *
 * @template G of Game
 * @template T of Team
 *
 * @use WithGame<G>
 */
#[PrimaryKey('id_player')]
#[Factory(PlayerFactory::class)] // @phpstan-ignore-line
abstract class Player extends Model
{
	/** @phpstan-use WithGame<G> */
	use WithGame;

	public const CACHE_TAGS    = ['players'];
	public const CLASSIC_BESTS = ['score', 'hits', 'score', 'accuracy', 'shots', 'miss'];
	public const SYSTEM        = '';

	#[Required]
	#[StringLength(1, 15)]
	public string     $name     = '';
	public int        $score    = 0;
	public int        $skill    = 0;
	public int|string $vest     = 0;
	public int        $shots    = 0;
	public int        $accuracy = 0;
	public int        $hits     = 0;
	public int        $deaths   = 0;
	public int        $position = 0;

	/** @var PlayerHit[] */
	#[NoDB]
	public array $hitPlayers = [];

	#[NoDB]
	public int $teamNum = 0;

	/** @var T|null */
	#[ManyToOne(foreignKey: 'id_team')]
	public ?Team  $team           = null;
	#[ManyToOne]
	public ?User  $user           = null;
	public ?float $relativeHits   = null;
	public ?float $relativeDeaths = null;
	protected int $color          = 0;
	/** @var Player<G,T>|null */
	protected ?Player $favouriteTarget = null;
	/** @var Player<G,T>|null */
	protected ?Player      $favouriteTargetOf = null;
	protected PlayerTrophy $trophy;

	public function __construct(?int $id = null, ?Row $dbRow = null) {
		$this->cacheTags[] = 'games/'.$this::SYSTEM;
		$this->cacheTags[] = 'players/'.$this::SYSTEM;
		parent::__construct($id, $dbRow);
	}

	/**
	 * @return bool
	 * @throws ValidationException
	 */
	public function save() : bool {
		try {
			/** @var int|null $test */
			$test = DB::select($this::TABLE, $this::getPrimaryKey())->where('id_game = %i && name = %s && vest = '.(is_string($this->vest) ? '%s' : '%i'), $this->game?->id, $this->name, $this->vest)->fetchSingle(cache: false);
			if (isset($test)) {
				$this->id = $test;
			}
			if (!isset($this->relativeHits)) {
				$this->getRelativeHits();
			}
			if (!isset($this->relativeDeaths)) {
				$this->getRelativeDeaths();
			}
		} catch (Throwable) {
		}

		//$this->calculateSkill();
		return parent::save();
	}

	/**
	 * @return float
	 * @throws Throwable
	 */
	public
	function getRelativeHits() : float {
		if (!isset($this->relativeHits)) {
			$expected = $this->getExpectedAverageHitCount();
			$diff = $this->hits - $expected;
			$this->relativeHits = 1 + ($diff / $expected);
		}
		return $this->relativeHits;
	}

	/**
	 * @return float
	 * @throws Throwable
	 */
	public function getRelativeDeaths() : float {
		if (!isset($this->relativeDeaths)) {
			$expected = $this->getExpectedAverageDeathCount();
			$diff = $this->deaths - $expected;
			$this->relativeDeaths = 1 + ($diff / $expected);
		}
		return $this->relativeDeaths;
	}

	/**
	 * Get the expected number of deaths based on enemy and teammate count for this player.
	 *
	 * We used regression to calculate the best model to describe the best model to predict the average number of hits based on the player's enemy and teammate count.
	 * We can easily calculate the expected average deaths count for each player based on our findings.
	 *
	 * @return float
	 * @throws Throwable
	 */
	public function getExpectedAverageDeathCount() : float {
		$enemyPlayerCount = $this->getGame()->getPlayerCount() - ($this->getGame()->mode?->isSolo() ? 1 : $this->getTeam()?->getPlayerCount());
		$teamPlayerCount = ($this->getTeam()?->getPlayerCount() ?? 1) - 1;
		if ($this->getGame()->mode?->isTeam()) {
			return (2.730673 * $enemyPlayerCount) + (-0.0566788 * $teamPlayerCount) + 43.203734389;
		}
		return (4.01628957539 * $enemyPlayerCount) - 15.0000175286;
	}

	/**
	 * @return T|null
	 */
	public function getTeam() : ?Team {
		return $this->team;
	}

	/**
	 * @return T|null
	 */
	public
	function getTeam() : ?Team {
		return $this->team;
	}

	/**
	 * @param T $team
	 *
	 * @return $this
	 */
	public function setTeam(Team $team) : static {
		$this->team = $team;
		$this->color = $this->team->color;
		//$team->getPlayers()->add($this);
		return $this;
	}

	/**
	 * @param T $team
	 *
	 * @return $this
	 */
	public
	function setTeam(Team $team) : static {
		$this->team = $team;
		$this->color = $this->team->color;
		//$team->getPlayers()->add($this);
		return $this;
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
	 * @throws Throwable
	 */
	public
	function calculateSkill() : int {
		$this->skill = (int) round($this->calculateBaseSkill());

		return $this->skill;
	}

	/**
	 * Calculate the base, not rounded skill level
	 *
	 * @return float
	 * @throws Throwable
	 */
	protected
	function calculateBaseSkill() : float {
		$skill = 0.0;

		$skill += $this->calculateSkillForHits();

		// Add points for K:D
		$skill += $this->calculateSkillFromKD();

		// Add points for accuracy - 100% accuracy <=> 500 points
		$skill += $this->calculateSkillFromAccuracy();

		return $skill;
	}

	/**
	 * @return float
	 * @throws Throwable
	 */
	protected
	function calculateSkillForHits() : float {
		$expectedAverageHits = $this->getExpectedAverageHitCount();
		$hitsDiff = $this->hits - $expectedAverageHits;

		// Normalize to value between <0,...) where the value of 1 corresponds to exactly average hit count
		$hitsDiffPercent = 1 + ($hitsDiff / $expectedAverageHits);

		// Completely average game should acquire at least 200 points
		$hitsSkill = $hitsDiffPercent * 200;

		// Normalize based on the game's length
		$gameLength = $this->getGame()->getRealGameLength();
		if ($gameLength !== 0.0) {
			$hitsSkill *= 15 / $gameLength;
		}
		return $hitsSkill;
	}

	/**
	 * @return float
	 * @throws Throwable
	 */
	protected
	function calculateSkillFromKD() : float {
		$kd = $this->getKd();
		$skill = 0.0;
		if ($kd >= 1) {
			$skill += $kd * 50;
		}
		else if ($kd !== 0.0) {
			$skill -= (1 / $kd) * 10;
		}

		// Add points for deviation from an average K:D
		$averageKd = $this->getGame()->getAverageKd();
		if ($averageKd === 0.0) {
			$averageKd = 1.0;
		}
		$kdDiff = 1 + (($kd - $averageKd) / $averageKd); // $average K:D should never be 0 if any hits were fired
		$skill += $kdDiff * 80;

		return $skill;
	}

	public
	function getKd() : float {
		return $this->hits / ($this->deaths === 0 ? 1 : $this->deaths);
	}

	/**
	 * @return float
	 */
	protected
	function calculateSkillFromAccuracy() : float {
		return 500 * ($this->accuracy / 100);
	}

	/**
	 * @return void
	 * @throws Throwable
	 */
	public
	function instantiateProperties() : void {
		parent::instantiateProperties();

		// Set the teamNum and color property
		$this->teamNum = $this->getTeamColor();
	}

	/**
	 * @return int
	 * @throws Throwable
	 */
	public
	function getTeamColor() : int {
		if (empty($this->color)) {
			$this->color = (isset($this->game) && $this->getGame()->mode?->isSolo() ? 2 : $this->getTeam()?->color) ?? 2;
		}
		return $this->color;
	}

	/**
	 * @return bool
	 */
	public
	function saveHits() : bool {
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
	public
	function getTodayPosition(string $property) : int {
		return 0; // TODO: Implement
	}

	/**
	 * Get missed shots
	 *
	 * @return int
	 */
	public
	function getMiss() : int {
		return $this->shots - $this->hits;
	}

	/**
	 * Get one trophy
	 *
	 * @return array{name:string,icon:string}
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public
	function getBestAt() : array {
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
	public
	function getAllBestAt() : array {
		if (!isset($this->trophy)) {
			$this->trophy = new PlayerTrophy($this);
		}
		return $this->trophy->getAll();
	}

	/**
	 * Get a player that this player hit the most
	 *
	 * @return Player<G,T>|null
	 * @throws DirectoryCreationException
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public
	function getFavouriteTarget() : ?Player {
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
	public
	function getHitsPlayers() : array {
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
	public
	function loadHits() : array {
		/** @var PlayerHit $className */
		$className = str_replace('Player', 'PlayerHit', get_class($this));
		$hits = DB::select($className::TABLE, 'id_target, count')->where('id_player = %i', $this->id)->fetchAll();
		foreach ($hits as $row) {
			$this->addHits($this::get($row->id_target), $row->count);
		}
		return $this->hitPlayers;
	}

	/**
	 * @param Player<G,T> $player
	 * @param int         $count
	 *
	 * @return $this
	 */
	public
	function addHits(Player $player, int $count = 1) : static {
		/** @var PlayerHit $className */
		$className = str_replace('Player', 'PlayerHit', get_class($this));
		if (isset($this->hitPlayers[$player->vest])) {
			$this->hitPlayers[$player->vest]->count += $count;
			return $this;
		}
		$this->hitPlayers[$player->vest] = new $className($this, $player, $count);
		return $this;
	}

	/**
	 * Get a player that hit this player the most
	 *
	 * @return Player<G,T>|null
	 * @throws DirectoryCreationException
	 * @throws ModelNotFoundException
	 * @throws Throwable
	 * @throws ValidationException
	 */
	public
	function getFavouriteTargetOf() : ?Player {
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
	 * @param Player<G,T> $player
	 *
	 * @return int
	 * @throws DirectoryCreationException
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public
	function getHitsPlayer(Player $player) : int {
		return $this->getHitsPlayers()[$player->vest]->count ?? 0;
	}

	/**
	 * @return array<string, mixed>
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 * @throws DirectoryCreationException
	 */
	public
	function jsonSerialize() : array {
		$data = parent::jsonSerialize();
		$data['user'] = $this->user?->id;
		if (isset($this->user)) {
			$data['code'] = $this->user->getCode();
			$data['rank'] = $this->user->rank;
		}
		$data['team'] = $this->getTeam()?->id;
		if (isset($data['game'])) {
			unset($data['game']);
		}
		$data['hitPlayers'] = $this->getHitsPlayers();
		$data['avgSkill'] = $this->getSkill();
		return $data;
	}

	/**
	 * Get the player's skill value. If the player is a part of a group, returns the average group value.
	 *
	 * @return int
	 */
	public
	function getSkill() : int {
		if (!isset($this->getGame()->group)) {
			return $this->skill;
		}
		try {
			$players = $this->getGame()->group->getPlayers();
			$name = Strings::toAscii($this->name);
			if (isset($players[$name])) {
				return $players[$name]->getSkill();
			}
		} catch (Throwable) {
		}
		return $this->skill;
	}

	/**
	 * @return int
	 */
	public
	function getColor() : int {
		return $this->color;
	}

	/**
	 * @return array<string,float>
	 * @throws Throwable
	 */
	public
	function getSkillParts() : array {
		return [
			'hits'     => $this->calculateSkillForHits(),
			'kd'       => $this->calculateSkillFromKD(),
			'accuracy' => $this->calculateSkillFromAccuracy(),
		];
	}

	public
	function getRankDifference() : ?float {
		if (!isset($this->user)) {
			return null;
		}
		return DB::select('player_game_rating', '[difference]')
						 ->where('[id_user] = %i AND [code] = %s', $this->user->id, $this->getGame()->code)
						 ->fetchSingle(false);
	}

}
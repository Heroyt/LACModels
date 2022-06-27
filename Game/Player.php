<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */
namespace App\GameModels\Game;

use App\Core\AbstractModel;
use App\Core\DB;
use App\Exceptions\ModelNotFoundException;
use App\Exceptions\ValidationException;
use App\GameModels\Traits\WithGame;
use Dibi\Exception;
use Lsr\Logging\Exceptions\DirectoryCreationException;

abstract class Player extends AbstractModel
{
	use WithGame;

	public const PRIMARY_KEY = 'id_player';

	public const DEFINITION = [
		'game'     => [
			'noTest'     => true,
			'validators' => ['required'],
			'class'      => Game::class,
		],
		'name'     => [
			'validators' => ['required'],
		],
		'score'    => [],
		'vest'     => [],
		'shots'    => [],
		'accuracy' => [],
		'hits'     => [],
		'deaths'   => [],
		'position' => [],
		'team'     => [
			'noTest' => true,
			'class'  => Team::class,
		],
	];

	public const CLASSIC_BESTS = ['score', 'hits', 'score', 'accuracy', 'shots', 'miss'];

	public int    $id_player = 0;
	public string $name      = '';
	public int    $score     = 0;
	public int    $vest      = 0;
	public int    $shots     = 0;
	public int    $accuracy  = 0;
	public int    $hits      = 0;
	public int    $deaths    = 0;
	public int    $position  = 0;

	/** @var PlayerHit[] */
	public array $hitPlayers = [];

	public int $teamNum = 0;

	protected ?Team        $team              = null;
	protected int          $color             = 0;
	protected ?Player      $favouriteTarget   = null;
	protected ?Player      $favouriteTargetOf = null;
	protected PlayerTrophy $trophy;

	/**
	 * @return bool
	 * @throws ValidationException
	 */
	public function save() : bool {
		$test = DB::select($this::TABLE, $this::PRIMARY_KEY)->where('id_game = %i && name = %s && vest = %i', $this->game->id, $this->name, $this->vest)->fetchSingle();
		if (isset($test)) {
			$this->id = $test;
		}
		return parent::save();
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
			return DB::replace($table, $values) > 0;
		} catch (Exception $e) {
			return false;
		}
	}

	public function getTodayPosition(string $property) : int {
		return 0; // TODO: Implement
	}

	public function getMiss() : int {
		return $this->shots - $this->hits;
	}

	/**
	 * @return array{name:string,icon:string}
	 * @throws DirectoryCreationException
	 * @throws ModelNotFoundException
	 */
	public function getBestAt() : array {
		if (!isset($this->trophy)) {
			$this->trophy = new PlayerTrophy($this);
		}
		return $this->trophy->getOne();
	}

	/**
	 * @return array{name:string,icon:string}[]
	 * @throws DirectoryCreationException
	 * @throws ModelNotFoundException
	 */
	public function getAllBestAt() : array {
		if (!isset($this->trophy)) {
			$this->trophy = new PlayerTrophy($this);
		}
		return $this->trophy->getAll();
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
		return $this;
	}

	/**
	 * Get a player that this player hit the most
	 *
	 * @return Player|null
	 * @throws DirectoryCreationException
	 * @throws ModelNotFoundException
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
	 * @throws DirectoryCreationException
	 */
	public function loadHits() : array {
		/** @var PlayerHit $className */
		$className = str_replace('Player', 'PlayerHit', get_class($this));
		$hits = DB::select($className::TABLE, 'id_target, count')->where('id_player = %i', $this->id)->fetchAll();
		foreach ($hits as $row) {
			/** @noinspection PhpParamsInspection */
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
	 */
	public function getFavouriteTargetOf() : ?Player {
		if (!isset($this->favouriteTargetOf)) {
			$max = 0;
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
	 */
	public function getHitsPlayer(Player $player) : int {
		return $this->getHitsPlayers()[$player->vest]?->count ?? 0;
	}

	/**
	 * @return int
	 */
	public function getTeamColor() : int {
		if (empty($this->color)) {
			$this->color = isset($this->game) && $this->getGame()->mode->isSolo() ? 2 : $this->getTeam()->color;
		}
		return $this->color;
	}

	public function jsonSerialize() : array {
		$data = parent::jsonSerialize();
		$data['team'] = $this->getTeam()?->id;
		if (isset($data['game'])) {
			unset($data['game']);
		}
		$data['hitPlayers'] = $this->getHitsPlayers();
		return $data;
	}

}
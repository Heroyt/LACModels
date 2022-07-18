<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game;

use App\Core\Collections\CollectionCompareFilter;
use App\Core\Collections\Comparison;
use App\Exceptions\GameModeNotFoundException;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\Enums\GameModeType;
use App\GameModels\Game\Evo5\BonusCounts;
use App\GameModels\Game\GameModes\AbstractMode;
use App\GameModels\Traits\WithPlayers;
use App\GameModels\Traits\WithTeams;
use App\Services\LigaApi;
use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Lsr\Core\App;
use Lsr\Core\Caching\Cache;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\Factory;
use Lsr\Core\Models\Attributes\Instantiate;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\NoDB;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;
use Lsr\Helpers\Tools\Strings;
use Nette\Caching\Cache as CacheParent;

/**
 * Base class for game models
 */
#[PrimaryKey('id_game')]
#[Factory(GameFactory::class)]
abstract class Game extends Model
{
	use WithPlayers;
	use WithTeams;

	public const SYSTEM = '';

	public ?DateTimeInterface $fileTime   = null;
	public ?DateTimeInterface $start      = null;
	public ?DateTimeInterface $importTime = null;
	public ?DateTimeInterface $end        = null;
	#[Instantiate]
	public ?Timing            $timing     = null;
	public string             $code;

	#[ManyToOne]
	public ?AbstractMode $mode     = null;
	public GameModeType  $gameType = GameModeType::TEAM;
	#[Instantiate]
	public ?Scoring      $scoring  = null;
	public bool          $sync     = false;

	#[NoDB]
	public bool $started  = false;
	#[NoDB]
	public bool $finished = false;

	public static function getTeamColors() : array {
		return [];
	}

	/**
	 * Create a new game from JSON data
	 *
	 * @param array $data
	 *
	 * @return Game
	 * @throws GameModeNotFoundException
	 * @throws ValidationException
	 * @throws ModelNotFoundException
	 */
	public static function fromJson(array $data) : Game {
		$game = new static();
		/** @var Player[] $players */
		$players = [];
		/** @var Team[] $teams */
		$teams = [];
		foreach ($data as $key => $value) {
			if (!property_exists($game, $key)) {
				continue;
			}
			switch ($key) {
				case 'gameType':
					$game->gameType = GameModeType::from($value);
					break;
				case 'lives':
				case 'ammo':
				case 'modeName':
				case 'fileNumber':
				case 'code':
				case 'respawn':
				case 'sync':
					$game->{$key} = $value;
					break;
				case 'end':
				case 'start':
					$timezone = new DateTimeZone($value['timezone']);
					$datetime = new DateTime($value['date']);
					$datetime->setTimezone($timezone);
					$game->{$key} = $datetime;
					break;
				case 'timing':
					$game->timing = new Timing(...$value);
					break;
				case 'scoring':
					$game->scoring = new Scoring(...$value);
					break;
				case 'mode':
					if (!isset($value['type'])) {
						$value['type'] = GameModeType::TEAM->value;
					}
					$game->mode = GameModeFactory::findByName($value['name'], GameModeType::from($value['type']) ?? GameModeType::TEAM, static::SYSTEM);
					break;
				case 'players':
				{
					foreach ($value as $playerData) {
						/** @var Player $player */
						$player = new ($game->playerClass);
						$player->setGame($game);
						$id = 0;
						foreach ($playerData as $keyPlayer => $valuePlayer) {
							if (!property_exists($player, $keyPlayer)) {
								continue;
							}
							switch ($keyPlayer) {
								case 'id':
								case 'id_player':
									$id = $valuePlayer;
									break;
								case 'name':
								case 'score':
								case 'shots':
								case 'accuracy':
								case 'vest':
								case 'hits':
								case 'deaths':
								case 'position':
								case 'shotPoints':
								case 'scoreBonus':
								case 'scorePowers':
								case 'scoreMines':
								case 'ammoRest':
								case 'minesHits':
								case 'hitsOther':
								case 'hitsOwn':
								case 'deathsOther':
								case 'deathsOwn':
									$player->{$keyPlayer} = $valuePlayer;
									break;
								case 'bonus':
									$player->bonus = new BonusCounts(...$valuePlayer);
									break;
							}
							$game->getPlayers()->add($player);
							$players[$id] = $player;
						}
					}
					break;
				}
				case 'teams':
				{
					foreach ($value as $teamData) {
						/** @var Team $team */
						$team = new $game->teamClass;
						$team->setGame($game);
						$id = 0;
						foreach ($teamData as $keyTeam => $valueTeam) {
							if (!property_exists($team, $keyTeam)) {
								continue;
							}
							switch ($keyTeam) {
								case 'id':
								case 'id_team':
									$id = $valueTeam;
									break;
								case 'name':
								case 'score':
								case 'color':
								case 'position':
									$team->{$keyTeam} = $valueTeam;
									break;
							}
							$game->addTeam($team);
							$teams[$id] = $team;
						}
					}
					break;
				}
			}
		}

		// Assign hits and teams
		foreach ($data['players'] ?? [] as $playerData) {
			$id = $playerData['id'] ?? $playerData['id_player'] ?? 0;
			if (!isset($players[$id])) {
				continue;
			}
			$player = $players[$id];
			// Hits
			foreach ($playerData['hitPlayers'] ?? [] as $hit) {
				if (isset($players[$hit['target']])) {
					$player->addHits($players[$hit['target']], $hit['count']);
				}
			}
			// Team
			$teamId = $playerData['team'] ?? 0;
			if (isset($teams[$teamId])) {
				$player->setTeam($teams[$teamId]);
				$teams[$teamId]->addPlayer($player);
			}
		}
		return $game;
	}

	public function isStarted() : bool {
		return $this->start !== null;
	}

	public function isFinished() : bool {
		return $this->end !== null && $this->importTime !== null;
	}

	/**
	 * Get best player by some property
	 *
	 * @param string $property
	 *
	 * @return Player|null
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public function getBestPlayer(string $property) : ?Player {
		$query = $this->getPlayers()->query()->sortBy($property);
		switch ($property) {
			case 'shots':
				$query->asc();
				break;
			case 'hitsOwn':
			case 'deathsOwn':
				$query->addFilter(new CollectionCompareFilter($property, Comparison::GREATER, 0));
			default:
				$query->desc();
				break;
		}
		return $query->first();
	}

	/**
	 * @return array<string,string>
	 */
	public function getBestsFields() : array {
		$fields = [
			'hits'     => lang('Největší terminátor', context: 'results.bests'),
			'deaths'   => lang('Objekt největšího zájmu', context: 'results.bests'),
			'score'    => lang('Absolutní vítěz', context: 'results.bests'),
			'accuracy' => lang('Hráč s nejlepší muškou', context: 'results.bests'),
			'shots'    => lang('Nejúspornější střelec', context: 'results.bests'),
			'miss'     => lang('Největší mimoň', context: 'results.bests'),
		];
		foreach ($fields as $key => $value) {
			$settingName = Strings::toCamelCase('best_'.$key);
			if (!($this->mode->settings->$settingName ?? true)) {
				unset($fields[$key]);
			}
		}
		return $fields;
	}

	/**
	 * Get player by vest number
	 *
	 * @param int $vestNum
	 *
	 * @return Player|null
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public function getVestPlayer(int $vestNum) : ?Player {
		return $this->getPlayers()->query()->filter('vest', $vestNum)->first();
	}

	/**
	 * @return array
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public function jsonSerialize() : array {
		$this->getTeams();
		$this->getPlayers();
		$data = parent::jsonSerialize();
		$data['players'] = $this->getPlayers()->getAll();
		$data['teams'] = $this->getTeams()->getAll();
		if (!isset($data['mode'])) {
			$data['mode'] = GameModeFactory::findByName(
				$this->gameType === GameModeType::TEAM ? 'Team deathmach' : 'Deathmach',
				$this->gameType,
				$this::SYSTEM
			);
			$this->mode = $data['mode'];
		}
		return $data;
	}

	/**
	 * Synchronize a game to public
	 *
	 * @return bool
	 */
	public function sync() : bool {
		/** @var LigaApi $liga */
		$liga = App::getService('liga');
		if ($liga->syncGames($this::SYSTEM, [$this])) {
			$this->sync = true;
			try {
				return $this->save();
			} catch (ValidationException) {
			}
		}
		return false;
	}

	public function save() : bool {
		$pk = $this::getPrimaryKey();
		/** @var object{id_game:int,code:string|null}|null $test */
		$test = DB::select($this::TABLE, $pk.', code')->where('start = %dt', $this->start)->fetch();
		if (isset($test)) {
			$this->id = $test->$pk;
			$this->code = $test->code;
		}
		if (empty($this->code)) {
			$this->code = uniqid('g', false);
		}
		$success = parent::save();
		if (!$success) {
			return false;
		}
		foreach ($this->getTeams() as $team) {
			$success &= $team->save();
		}
		if (!$success) {
			return false;
		}
		if ($this->getTeams()->count() === 0) {
			foreach ($this->getPlayers() as $player) {
				$success &= $player->save();
			}
		}
		return $success;
	}

	public function update() : bool {
		// Invalidate cache
		/** @var Cache $cache */
		$cache = App::getService('cache');
		$cache->remove('games/'.$this::SYSTEM.'/'.$this->id);
		$cache->clean([CacheParent::Tags => ['games/'.$this::SYSTEM.'/'.$this->id, 'games/'.$this->start->format('Y-m-d')]]);
		return parent::update();
	}

	public function delete() : bool {
		// Invalidate cache
		/** @var Cache $cache */
		$cache = App::getService('cache');
		$cache->remove('games/'.$this::SYSTEM.'/'.$this->id);
		$cache->clean([CacheParent::Tags => ['games/'.$this::SYSTEM.'/'.$this->id, 'games/'.$this->start->format('Y-m-d')]]);
		return parent::delete();
	}

}
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
use App\Models\Arena;
use App\Models\MusicMode;
use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Dibi\Row;
use JsonException;
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
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Nette\Caching\Cache as CacheParent;

/**
 * Base class for game models
 *
 * @phpstan-consistent-constructor
 */
#[PrimaryKey('id_game')]
#[Factory(GameFactory::class)] // @phpstan-ignore-line
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
	#[ManyToOne]
	public ?Arena $arena = null;

	#[ManyToOne]
	public ?MusicMode $music = null;

	#[NoDB]
	public bool     $started  = false;
	#[NoDB]
	public bool     $finished = false;
	protected float $realGameLength;

	/**
	 * @return array<int, string>
	 */
	public static function getTeamColors() : array {
		return [];
	}

	/**
	 * @return array<int, string>
	 */
	public static function getTeamNames() : array {
		return [];
	}

	/**
	 * Create a new game from JSON data
	 *
	 * @param array{
	 *     gameType?: string,
	 *     lives?: int,
	 *     ammo?: int,
	 *     modeName?: string,
	 *     fileNumber?: int,
	 *     code?: string,
	 *     respawn?: int,
	 *     sync?: int|bool,
	 *     start?: array{date:string,timezone:string},
	 *     end?: array{date:string,timezone:string},
	 *     timing?: array<string,int>,
	 *     scoring?: array<string,int>,
	 *     mode?: array{type?:string,name:string},
	 *     players?: array{
	 *         id?: int,
	 *         id_player?: int,
	 *         name?: string,
	 *         score?: int,
	 *         skill?: int,
	 *         shots?: int,
	 *         accuracy?: int,
	 *         vest?: int,
	 *         hits?: int,
	 *         deaths?: int,
	 *         hitsOwn?: int,
	 *         hitsOther?: int,
	 *         deathsOwn?: int,
	 *         deathsOther?: int,
	 *         position?: int,
	 *         shotPoints?: int,
	 *         scoreBonus?: int,
	 *         scoreMines?: int,
	 *         ammoRest?: int,
	 *         bonus?: array<string, int>,
	 *     }[],
	 *   teams?: array{
	 *         id?: int,
	 *         id_team?: int,
	 *         name?: string,
	 *         score?: int,
	 *         color?: int,
	 *         position?: int,
	 *     }[],
	 * } $data
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
				/* @phpstan-ignore-next-line */
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
					$game->mode = GameModeFactory::findByName($value['name'], GameModeType::tryFrom($value['type']) ?? GameModeType::TEAM, static::SYSTEM);
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
								case 'skill':
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
								case 'teamNum':
									/* @phpstan-ignore-next-line */
									$player->{$keyPlayer} = $valuePlayer;
									break;
								case 'bonus':
									/* @phpstan-ignore-next-line */
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
									/* @phpstan-ignore-next-line */
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
		/* @phpstan-ignore-next-line */
		foreach (($data['players'] ?? []) as $playerData) {
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
			$teamId = (int) ($playerData['team'] ?? 0);
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

	/**
	 * Get best player by some property
	 *
	 * @param string $property
	 *
	 * @return Player|null
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 * @noinspection PhpMissingBreakStatementInspection
	 */
	public function getBestPlayer(string $property) : ?Player {
		$query = $this->getPlayers()->query()->sortBy($property);
		switch ($property) {
			case 'shots':
				$query->asc();
				break;
			case 'hitsOwn':
			case 'deathsOwn':
			/* @phpstan-ignore-next-line */
				$query->addFilter(new CollectionCompareFilter($property, Comparison::GREATER, 0));
			default:
				$query->desc();
				break;
		}
		return $query->first();
	}

	/**
	 * @return array<string,string>
	 * @noinspection PhpArrayShapeAttributeCanBeAddedInspection
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
	 * @return array<string, mixed>
	 * @throws GameModeNotFoundException
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
	 * @return bool
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 * @noinspection PhpUndefinedFieldInspection
	 */
	public function save() : bool {
		$pk = $this::getPrimaryKey();
		/** @var Row|null $test */
		$test = DB::select($this::TABLE, $pk.', code')->where('start = %dt', $this->start)->fetch();
		if (isset($test)) {
			/** @noinspection PhpFieldAssignmentTypeMismatchInspection */
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

		$this->calculateSkills();

		foreach ($this->getTeams() as $team) {
			$success &= $team->save();
		}
		if (!$success) {
			return false;
		}
		if ($this->getTeams()->count() === 0) {
			/** @var Player $player */
			foreach ($this->getPlayers() as $player) {
				$success = $success && $player->save();
			}
		}
		/* @phpstan-ignore-next-line */
		return $success;
	}

	public function calculateSkills() : void {
		/** @var Player[] $players */
		$players = $this->getPlayers()->getAll();

		// Calculate the base skill for all players first
		$skills = [];
		foreach ($players as $player) {
			$skills[] = $player->calculateSkill();
		}
		// -1 because we will always subtract one player while calculating the average
		$playerCount = count($skills) - 1;
		if ($playerCount === 0) {
			return;
		}
		$skillSum = array_sum($skills);

		// Modulate the skill value based on the average skill value for each player.
		// This should lower the skill value for players, if they are playing against weak opponents and vice versa.
		foreach ($players as $player) {
			// Recalculate the average skill of all other players using the skill sum
			$avg = ($skillSum - $player->skill) / $playerCount;
			// Negative if the player skill is greater than the average and vice versa
			$diff = $avg - $player->skill;
			if ($avg === 0) {
				$avg = 1;
			}
			$diffPercent = abs($diff / $avg);

			// 1-(1/x) has an asymptote in y=1, therefore it is never possible to lower the skill value by 100%.
			$percent = 1 - (8 / ($diffPercent + 8));
			$newDiff = (int) abs(round($player->skill * $percent));
			if ($diff < 0) {
				$player->skill -= $newDiff;
			}
			else {
				$player->skill += $newDiff;
			}
		}
	}

	public function update() : bool {
		// Invalidate cache
		/** @var Cache $cache */
		$cache = App::getService('cache');
		$cache->remove('games/'.$this::SYSTEM.'/'.$this->id);
		$cache->clean([CacheParent::Tags => ['games/'.$this::SYSTEM.'/'.$this->id, 'games/'.$this->start?->format('Y-m-d')]]);
		return parent::update();
	}

	public function delete() : bool {
		// Invalidate cache
		/** @var Cache $cache */
		$cache = App::getService('cache');
		$cache->remove('games/'.$this::SYSTEM.'/'.$this->id);
		$cache->clean([CacheParent::Tags => ['games/'.$this::SYSTEM.'/'.$this->id, 'games/'.$this->start?->format('Y-m-d')]]);
		return parent::delete();
	}

	/**
	 * Get the real game length in minutes.
	 *
	 * @return float Real game length in minutes.
	 */
	public function getRealGameLength() : float {
		if (!isset($this->realGameLength)) {
			if (!isset($this->end, $this->start) || !$this->isFinished()) {
				// If the game is not finished, it does not have a game length
				return 0;
			}
			$diff = $this->start->diff($this->end);
			$this->realGameLength = (($diff->h * 3600) + ($diff->i * 60) + $diff->s) / 60;
		}
		return $this->realGameLength;
	}

	public function isFinished() : bool {
		return $this->end !== null && $this->importTime !== null;
	}

	/**
	 * @return float
	 */
	public function getAverageKd() : float {
		try {
			/** @var float[] $kds */
			$kds = $this->getPlayers()->query()->map(fn(Player $player) => $player->getKd())->get();
		} catch (ModelNotFoundException|ValidationException|DirectoryCreationException $e) {
			return 1;
		}
		return empty($kds) ? 1 : array_sum($kds) / count($kds);
	}

	public function recalculateScores() : void {
		if (isset($this->mode)) {
			$this->mode->recalculateScores($this);
			$this->reorder();
		}
	}

	public function reorder() : void {
		if (isset($this->mode)) {
			$this->mode->reorderGame($this);
		}
	}

}
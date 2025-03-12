<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game;

use App\Core\App;
use App\Core\Collections\CollectionCompareFilter;
use App\Core\Collections\Comparison;
use App\Exceptions\GameModeNotFoundException;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\GameModes\AbstractMode;
use App\GameModels\Traits\WithPlayers;
use App\GameModels\Traits\WithTeams;
use App\Models\BaseModel;
use App\Models\Arena;
use App\Models\Auth\LigaPlayer;
use App\Models\DataObjects\Import\GameImportDto;
use App\Models\DataObjects\Import\TeamColorImportDto;
use App\Models\GameGroup;
use App\Models\MusicMode;
use App\Models\Tournament\Game as TournamentGame;
use DateTimeImmutable;
use App\Models\WithMetaData;
use App\Services\FeatureConfig;
use DateTimeInterface;
use Dibi\Row;
use LAC\Modules\Tables\Models\Table;
use Lsr\Caching\Cache;
use Lsr\Core\Config;
use Lsr\Db\DB;
use Lsr\Helpers\Tools\Strings;
use Lsr\Lg\Results\Enums\GameModeType;
use Lsr\Lg\Results\Interface\Models\GameGroupInterface;
use Lsr\Lg\Results\Interface\Models\GameInterface;
use Lsr\Lg\Results\Interface\Models\GameModeInterface;
use Lsr\Lg\Results\Interface\Models\MusicModeInterface;
use Lsr\Lg\Results\Timing;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\ObjectValidation\Attributes\NoValidate;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\Instantiate;
use Lsr\Orm\Attributes\NoDB;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\LoadingType;
use Nette\Caching\Cache as CacheParent;
use OpenApi\Attributes as OA;
use Throwable;

/**
 * Base class for game models
 *
 * @phpstan-type GameMeta array{
 *       music:null|numeric,
 *       mode:string,
 *       loadTime:int,
 *       group?:int,
 *       table?:int,
 *       variations?:array<int,string>,
 *       hash: string,
 *  }
 *
 * @phpstan-consistent-constructor
 * @template T of Team
 * @template P of Player
 *
 * @use WithTeams<T>
 * @use WithPlayers<P>
 */
#[PrimaryKey('id_game')]
#[Factory(GameFactory::class)] // @phpstan-ignore-line
#[OA\Schema(schema: 'Game')]
abstract class Game extends BaseModel implements GameInterface
{
	/** @phpstan-use WithPlayers<P> */
	use WithPlayers;

	/** @phpstan-use WithTeams<T> */
	use WithTeams;
	use WithMetaData;

	/** @var 'evo5'|'evo6'|'laserforce'|string */
	public const string   SYSTEM            = '';
	public const array    CACHE_TAGS        = ['games'];
	protected const array IMPORT_PROPERTIES = [
		'resultsFile',
		'fileTime',
		'modeName',
		'importTime',
		'start',
		'end',
		'gameType',
		'code,',
	];

	public ?string $resultsFile = null;
	public string  $modeName;

	#[OA\Property]
	public ?DateTimeInterface $fileTime   = null;
	#[OA\Property]
	public ?DateTimeInterface $start      = null;
	#[OA\Property]
	public ?DateTimeInterface $importTime = null;
	#[OA\Property]
	public ?DateTimeInterface $end        = null;
	#[Instantiate]
	#[OA\Property]
	public ?Timing            $timing     = null;
	#[OA\Property]
	public string             $code;

	#[OA\Property, ManyToOne(loadingType: LoadingType::LAZY)]
	public ?AbstractMode $mode;
	#[OA\Property]
	public GameModeType  $gameType = GameModeType::TEAM;
	#[ManyToOne, OA\Property]
	public ?Arena        $arena    = null;

	#[ManyToOne(class: MusicMode::class), OA\Property, NoValidate]
	public ?MusicModeInterface $music = null;
	#[ManyToOne(class: GameGroup::class), OA\Property, NoValidate]
	public ?GameGroupInterface $group = null;

	#[NoDB, OA\Property]
	public bool     $started  = false;
	#[NoDB, OA\Property]
	public bool     $finished = false;
	public bool     $visited  = false;
	protected float $realGameLength;

	#[NoDB]
	public ?TournamentGame $tournamentGame = null;

	public function __construct(?int $id = null, ?Row $dbRow = null) {
		$this->cacheTags[] = 'games/' . $this::SYSTEM;
		parent::__construct($id, $dbRow);
	}

	/**
	 * @return array<int, string>
	 */
	public static function getTeamColors(): array {
		return [];
	}

	/**
	 * @return array<int, string>
	 */
	public static function getTeamNames(): array {
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
	 *     start?: array{date:string,timezone?:string}|string,
	 *     end?: array{date:string,timezone?:string}|string,
	 *     timing?: array<string,int>,
	 *     scoring?: array<string,int>,
	 *     mode?: array{type?:string,name:string},
	 *     players?: array{
	 *         id?: int,
	 *         id_player?: int,
	 *         name?: string,
	 *         code?: string,
	 *         team?: int|array{id?:int,color?:int}|mixed,
	 *         score?: int,
	 *         skill?: int,
	 *         shots?: int,
	 *         accuracy?: int,
	 *         vest?: int,
	 *         hits?: int,
	 *         deaths?: int,
	 *         hitsOwn?: int,
	 *         hitsOther?: int,
	 *         hitPlayers?: array{target:int,count:int}[],
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
	 * @throws DirectoryCreationException
	 * @throws GameModeNotFoundException
	 * @throws ModelNotFoundException
	 * @throws Throwable
	 * @throws ValidationException
	 */
	public static function fromJson(array $data): Game {
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
				case 'fileNumber':
				case 'code':
				case 'respawn':
				case 'sync':
					/* @phpstan-ignore-next-line */
					$game->{$key} = $value;
					break;
				case 'end':
				case 'start':
					if (is_string($value)) {
						$datetime = new DateTimeImmutable(($value));
					}
					else {
						$timezone = new DateTimeZone($value['timezone'] ?? 'Europe/Prague');
						$datetime = new DateTimeImmutable($value['date'], $timezone);
					}
					$game->{$key} = $datetime;
					break;
				case 'timing':
					$game->timing = new Timing(...$value);
					break;
				case 'scoring':
					assert(property_exists($game, 'scoring'));
					$game->scoring = new Scoring(...$value);
					break;
				case 'modeName':
					$game->modeName = $value;
					break;
				case 'mode':
					if (!isset($value['type'])) {
						$value['type'] = GameModeType::TEAM->value;
					}
					$game->mode = GameModeFactory::findByName(
						$value['name'],
						GameModeType::tryFrom(
							$value['type']
						) ?? GameModeType::TEAM,
						static::SYSTEM
					);
					if (!empty($data['modeName'])) {
						$mode = GameModeFactory::find(
							$data['modeName'],
							GameModeType::tryFrom(
								$data['gameType'] ?? ''
							) ?? GameModeType::TEAM,
							static::SYSTEM
						);
						$game->mode = $mode;
					}
					break;
				case 'players':
				{
					foreach ($value as $playerData) {
						/** @var P $player */
						$player = new ($game->playerClass);
						$player->setGame($game);
						$id = 0;
						foreach ($playerData as $keyPlayer => $valuePlayer) {
							if ($keyPlayer !== 'code' && !property_exists($player, $keyPlayer)) {
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
									/* @phpstan-ignore-next-line */
									$player->{$keyPlayer} = $valuePlayer;
									break;
								case 'team':
									if (is_numeric($valuePlayer)) {
										$player->teamNum = (int)$valuePlayer;
									}
									else if (is_array($valuePlayer) && array_key_exists('color', $valuePlayer)) {
										$player->teamNum = (int)$valuePlayer['color'];
									}
									break;
								case 'bonus':
									/* @phpstan-ignore-next-line */
									$player->bonus = new BonusCounts(
										$valuePlayer['agent'] ?? 0,
										$valuePlayer['invisibility'] ?? 0,
										$valuePlayer['machineGun'] ?? 0,
										$valuePlayer['shield'] ?? 0,
									);
									break;
								case 'code':
									$player->user = LigaPlayer::getByCode($valuePlayer);
									break;
								case 'tournamentPlayer':
									if (((int)$valuePlayer) > 0) {
										try {
											$player->tournamentPlayer = \App\Models\Tournament\Player::get(
												(int)$valuePlayer
											);
										} catch (ModelNotFoundException) {
										}
									}
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
						/** @var T $team */
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
								case 'bonus':
								case 'color':
								case 'position':
									$team->{$keyTeam} = $valueTeam;
									break;
								case 'tournamentTeam':
									if (((int)$valueTeam) > 0) {
										try {
											$team->tournamentTeam = \App\Models\Tournament\Team::get(
												(int)$valueTeam
											);
										} catch (ModelNotFoundException) {
										}
									}
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

		if (!isset($game->mode)) {
			$game->getMode();
		}

		// Assign hits and teams
		$game->getLogger()->debug('Teams - ' . json_encode($teams));
		foreach (($data['players'] ?? []) as $playerData) {
			$id = $playerData['id'] ?? $playerData['id_player'] ?? 0;
			if (!isset($players[$id])) {
				continue;
			}
			$player = $players[$id];
			// Hits
			foreach (($playerData['hitPlayers'] ?? []) as $hit) {
				if (isset($players[$hit['target']])) {
					$player->addHits($players[$hit['target']], $hit['count']);
				}
			}
			// Team
			$teamId = null;
			if (isset($playerData['team'])) {
				if (is_numeric($playerData['team'])) {
					$teamId = (int)$playerData['team'];
				}
				else if (is_array($playerData['team']) && array_key_exists('id', $playerData['team'])) {
					$teamId = (int)$playerData['team']['id'];
				}
			}
			$game->getLogger()->debug('Player - ' . $player->vest . ' - team ' . json_encode($teamId));
			if (isset($teamId, $teams[$teamId])) {
				$player->setTeam($teams[$teamId]);
				$teams[$teamId]->addPlayer($player);
			}
		}
		return $game;
	}

	/**
	 * @param GameImportDto $data
	 *
	 * @return static
	 * @throws GameModeNotFoundException
	 */
	public static function fromImportDto(GameImportDto $data): Game {
		$game = new static();
		/** @var Player[] $players */
		$players = [];
		/** @var Team[] $teams */
		$teams = [];
		foreach (static::IMPORT_PROPERTIES as $property) {
			if (isset($data->{$property})) {
				$game->{$property} = $data->{$property};
			}
		}
		if (isset($data->mode)) {
			$game->mode = GameModeFactory::findByName(
				$data->mode->name,
				$data->mode->type ?? GameModeType::TEAM,
				static::SYSTEM
			);
			if (!empty($data->modeName)) {
				$mode = GameModeFactory::find(
					$data->modeName,
					$data->gameType ?? GameModeType::TEAM,
					static::SYSTEM
				);
				$game->mode = $mode;
			}
		}

		foreach ($data->players as $playerData) {
			/** @var P $player */
			$player = ($game->playerClass)::fromImportDto($playerData);
			$player->setGame($game);
			$id = $playerData->id ?? $playerData->id_player ?? 0;
			$game->addPlayer($player);
			$players[$id] = $player;
		}

		foreach ($data->teams as $teamData) {
			/** @var T $team */
			$team = ($game->teamClass)::fromImportDto($teamData);
			$team->setGame($game);
			$id = $teamData->id ?? $teamData->id_player ?? 0;
			$game->addTeam($team);
			$teams[$id] = $team;
		}

		if (!isset($game->mode)) {
			$game->getMode();
		}

		// Assign hits and teams
		foreach ($data->players as $playerData) {
			$id = $playerData->id ?? $playerData->id_player ?? 0;
			if (!isset($players[$id])) {
				continue;
			}
			$player = $players[$id];

			// Hits
			foreach ($playerData->hitPlayers as $hit) {
				if (isset($players[$hit->target])) {
					$player->addHits($players[$hit->target], $hit->count);
				}
			}

			// Team
			$teamId = null;
			if (is_numeric($playerData->team)) {
				$teamId = (int)$playerData->team;
			}
			else if ($playerData->team instanceof TeamColorImportDto) {
				$teamId = $playerData->team->id;
			}
			$game->getLogger()->debug('Player - ' . $player->vest . ' - team ' . json_encode($teamId));
			if (isset($teamId, $teams[$teamId])) {
				$player->setTeam($teams[$teamId]);
				$teams[$teamId]->addPlayer($player);
			}
		}
		return $game;
	}

	/**
	 * @return AbstractMode|null
	 * @throws GameModeNotFoundException
	 */
	public function getMode(): ?AbstractMode {
		if (!isset($this->mode, $this->mode->id)) {
			$this->mode = null;
			if (isset($this->relationIds['mode'])) {
				$this->mode = GameModeFactory::getById($this->relationIds['mode']);
			}
			if (!isset($this->mode) && isset($this->modeName)) {
				$this->mode = GameModeFactory::find($this->modeName, $this->gameType, $this::SYSTEM);
			}
		}
		return $this->mode;
	}

	/**
	 * @return AbstractMode|null
	 * @throws GameModeNotFoundException
	 */
	public function getMode(): ?AbstractMode {
		if (!isset($this->mode)) {
			if (isset($this->relationIds['mode'])) {
				$this->mode = GameModeFactory::getById($this->relationIds['mode']);
			}
			else {
				if (isset($this->modeName)) {
					$this->mode = GameModeFactory::find($this->modeName, $this->gameType, $this::SYSTEM);
				}
				else {
					$this->mode = null;
				}
			}
		}
		return $this->mode;
	}

	public function loadMode(): ?AbstractMode {
		if (isset($this->relationIds['mode'])) {
			$mode = GameModeFactory::getById($this->relationIds['mode'], ['system' => $this::SYSTEM]);
			if ($mode !== null) {
				return $mode;
			}
		}

		if (isset($this->modeName)) {
			return GameModeFactory::find($this->modeName, $this->gameType, $this::SYSTEM);
		}
		return GameModeFactory::findModeObject($this::SYSTEM, null, $this->gameType);
	}

	public function isStarted(): bool {
		return $this->start !== null;
	}

	/**
	 * Get best player by some property
	 *
	 * @param string $property
	 *
	 * @return Player|null
	 * @throws  ValidationException
	 * @noinspection PhpMissingBreakStatementInspection
	 */
	public function getBestPlayer(string $property): ?Player {
		$query = $this->players->query()->sortBy($property);
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
	public function getBestsFields(): array {
		$fields = [
			'hits'     => lang('Největší terminátor', context: 'bests', domain: 'results'),
			'deaths'   => lang('Objekt největšího zájmu', context: 'bests', domain: 'results'),
			'score'    => lang('Absolutní vítěz', context: 'bests', domain: 'results'),
			'accuracy' => lang('Hráč s nejlepší muškou', context: 'bests', domain: 'results'),
			'shots'    => lang('Nejúspornější střelec', context: 'bests', domain: 'results'),
			'miss'     => lang('Největší mimoň', context: 'bests', domain: 'results'),
		];
		foreach ($fields as $key => $value) {
			$settingName = Strings::toCamelCase('best_' . $key);
			if (!($this->mode->settings->$settingName ?? true)) {
				unset($fields[$key]);
			}
		}
		return $fields;
	}

	/**
	 * Get player by vest number
	 *
	 * @param int|string $vestNum
	 *
	 * @return Player|null
	 */
	public function getVestPlayer(int|string $vestNum): ?Player {
		return $this->players->query()->filter('vest', $vestNum)->first();
	}

	/**
	 * @return array<string, mixed>
	 * @throws DirectoryCreationException
	 * @throws GameModeNotFoundException
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public function jsonSerialize(): array {
		$data = parent::jsonSerialize();
		$data['system'] = $this::SYSTEM;
		$data['players'] = $this->players->getAll();
		$data['playerCount'] = $this->playerCount;
		$data['teams'] = $this->teams->getAll();
		$data['group'] = null;
		if ($this->group !== null) {
			$data['group'] = [
				'id'     => $this->group->id,
				'name'   => $this->group->name,
				'active' => $this->group->active,
			];
		}
		if (isset($data['tournamentGame'])) {
			unset($data['tournamentGame']);
		}
		if (!isset($data['music'])) {
			$data['music'] = $this->music;
		}
		$data['metaData'] = $this->getMeta();
		$data['mode'] = $this->mode?->jsonSerialize();
		if (isset($data['mode'])) {
			$data['mode']['variations'] = $this->getMeta()['variations'] ?? [];
		}
		return $data;
	}

	/**
	 * @return bool
	 * @throws DirectoryCreationException
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 * @throws Throwable
	 * @noinspection PhpUndefinedFieldInspection
	 */
	public function save(): bool {
		$pk = $this::getPrimaryKey();
		/** @var Row|null $test */
		$test = DB::select($this::TABLE, $pk . ', code')
		          ->where(
			          'start = %dt OR start = %dt',
			          $this->start,
			          $this->start->getTimestamp() + ($this->timing->before ?? 20)
		          )
		          ->fetch(cache: false);
		if (isset($test)) {
			$this->id = $test->$pk;
			$this->code = $test->code;
		}
		if (empty($this->code)) {
			$this->code = uniqid($this->arena->gameCodePrefix ?? 'g', false);
		}
		$success = parent::save();
		if (!$success) {
			return false;
		}

		$this->calculateSkills();

		foreach ($this->teams as $team) {
			$success &= $team->save();
		}
		if (!$success) {
			return false;
		}
		if ($this->teams->count() === 0) {
			/** @var Player $player */
			foreach ($this->players as $player) {
				$success = $success && $player->save();
			}
		}

		if ($this->getGroup() !== null) {
			$success = $success && $this->getGroup()->save();
		}

		if ($this->getTournamentGame() !== null) {
			$this->tournamentGame->code = $this->code;
			$this->tournamentGame->save();
		}

		/* @phpstan-ignore-next-line */
		return $success;
	}

	/**
	 * @return void
	 * @throws Throwable
	 */
	public function calculateSkills(): void {
		/** @var Player[] $players */
		$players = $this->players->getAll();

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
			$newDiff = (int)abs(round($player->skill * $percent));
			if ($diff < 0) {
				$player->skill -= $newDiff;
			}
			else {
				$player->skill += $newDiff;
			}
		}
	}

	public function insert(): bool {
		if ($this->group !== null) {
			$this->group->clearCache();
		}
		/** @var Cache $cache */
		$cache = App::getService('cache');
		$cache->clean(
			[
				CacheParent::Tags => [
					'games/counts',
					'arena/' . $this->arena?->id . '/games',
					'arena/' . $this->arena?->id . '/games/' . $this->start?->format('Y-m-d'),
				],
			]
		);
		return parent::insert();
	}

	public function clearCache(): void {
		parent::clearCache();

		// Invalidate cached objects
		/** @var Cache $cache */
		$cache = App::getService('cache');
		$cache->remove('games/' . $this::SYSTEM . '/' . $this->id);
		$cache->clean(
			[
				CacheParent::Tags => [
					'games/' . $this::SYSTEM . '/' . $this->id,
					'games/' . $this->start?->format('Y-m-d'),
					'games/' . $this->start?->format('Y-m'),
					'games/' . $this->start?->format('Y'),
					'games/' . $this->code,
					'arena/' . $this->arena?->id . '/games',
					'arena/' . $this->arena?->id . '/games/' . $this->start?->format('Y-m-d'),
				],
			]
		);

		if ($this->group !== null) {
			$this->group->clearCache();
		}

		// Invalidate generated results cache
		$files = glob(TMP_DIR . 'results/' . $this->code . '*');
		if ($files !== false) {
			foreach ($files as $file) {
				@unlink($file);
			}
		}
	}

	public function delete(): bool {
		$this->clearCache();
		return parent::delete();
	}

	/**
	 * Get the real game length in minutes.
	 *
	 * @return float Real game length in minutes.
	 */
	public function getRealGameLength(): float {
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

	public function isFinished(): bool {
		return $this->end !== null;
	}

	/**
	 * @return float
	 */
	public function getAverageKd(): float {
		try {
			/** @var float[] $kds */
			$kds = $this->players->query()->map(fn(Player $player) => $player->getKd())->get();
		} catch (ValidationException|DirectoryCreationException) {
			return 1;
		}
		return empty($kds) ? 1 : array_sum($kds) / count($kds);
	}

	/**
	 * @return void
	 * @throws GameModeNotFoundException
	 */
	public function recalculateScores(): void {
		if ($this->mode !== null) {
			$this->mode->recalculateScores($this);
			$this->reorder();
		}
	}

	public function reorder(): void {
		$this->mode?->reorderGame($this);
	}

	/**
	 * @return GameGroup|null
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public function getGroup(): ?GameGroup {
		$this->group ??= isset($this->relationIds['group']) ? GameGroup::get($this->relationIds['group']) : null;
		return $this->group;
	}

	/**
	 * @return MusicMode|null
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public function getMusic(): ?MusicMode {
		$this->music ??= isset($this->relationIds['music']) ? MusicMode::get($this->relationIds['music']) : null;
		return $this->music;
	}

	/**
	 * @return TournamentGame|null
	 */
	public function getTournamentGame(): ?TournamentGame {
		if (!isset($this->tournamentGame)) {
			$this->tournamentGame = TournamentGame::query()->where('[code] = %s', $this->code)->first();
		}
		return $this->tournamentGame;
	}

	public function codeToNum(): int {
		$num = 0;
		foreach (str_split($this->code) as $char) {
			$num += ord($char);
		}
		return $num;
	}

	public function getAverageDeaths(): float {
		$sum = 0;
		foreach ($this->getPlayers() as $player) {
			$sum += $player->deaths;
		}
		return $sum / $this->getPlayerCount();
	}

	public function getAverageHits(): float {
		$sum = 0;
		foreach ($this->getPlayers() as $player) {
			$sum += $player->hits;
		}
		return $sum / $this->getPlayerCount();
	}

}
<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game;

use App\Exceptions\GameModeNotFoundException;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\GameModes\AbstractMode;
use App\GameModels\Traits\WithPlayers;
use App\GameModels\Traits\WithTeams;
use App\Models\Arena;
use App\Models\BaseModel;
use App\Models\DataObjects\Import\GameImportDto;
use App\Models\DataObjects\Import\TeamColorImportDto;
use App\Models\GameGroup;
use App\Models\MusicMode;
use App\Models\SystemType;
use App\Models\Tournament\Game as TournamentGame;
use App\Models\WithMetaData;
use DateTimeInterface;
use Dibi\Row;
use Lsr\Caching\Cache;
use Lsr\Core\App;
use Lsr\Db\DB;
use Lsr\Helpers\Tools\Strings;
use Lsr\Lg\Results\Collections\CollectionCompareFilter;
use Lsr\Lg\Results\Enums\Comparison;
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
use Lsr\Orm\Attributes\Hooks\AfterDelete;
use Lsr\Orm\Attributes\Hooks\AfterInsert;
use Lsr\Orm\Attributes\Hooks\AfterUpdate;
use Lsr\Orm\Attributes\Instantiate;
use Lsr\Orm\Attributes\NoDB;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Lsr\Orm\LoadingType;
use Nette\Caching\Cache as CacheParent;
use OpenApi\Attributes as OA;
use Random\Randomizer;
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

	/** @var value-of<SystemType> */
	public const string   SYSTEM = 'evo5';
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

	#[OA\Property]
	public ?string $resultsFile = null;
	#[OA\Property]
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

	/** @var non-empty-string  */
	#[OA\Property]
	public string             $code;

	/** @var AbstractMode|null $mode  */
	#[OA\Property, ManyToOne(class: AbstractMode::class, loadingType: LoadingType::EAGER, factoryMethod: 'loadMode'), NoValidate]
	public ?GameModeInterface $mode;
	#[OA\Property]
	public GameModeType  $gameType = GameModeType::TEAM;
	#[ManyToOne, OA\Property]
	public ?Arena        $arena    = null;

	/** @var MusicMode|null */
	#[ManyToOne(class: MusicMode::class), OA\Property, NoValidate]
	public ?MusicModeInterface $music = null;
	/** @var GameGroup|null  */
	#[ManyToOne(class: GameGroup::class), OA\Property, NoValidate]
	public ?GameGroupInterface $group = null;

	#[NoDB, OA\Property]
	public bool     $started  = false;
	#[NoDB, OA\Property]
	public bool     $finished = false;
	#[OA\Property]
	public bool     $visited  = false;
	protected float $realGameLength;
	#[OA\Property]
	public ?string $photosSecret = null;
	#[OA\Property]
	public bool $photosPublic = false;

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
			$game->players->set($player, (int) $player->vest);
			$players[$id] = $player;
		}

		foreach ($data->teams as $teamData) {
			/** @var T $team */
			$team = ($game->teamClass)::fromImportDto($teamData);
			$team->setGame($game);
			$id = $teamData->id ?? $teamData->id_player ?? 0;
			$game->teams->set($team, $team->color);
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
				$player->team = $teams[$teamId];
				$teams[$teamId]->players->set($player, (int) $player->vest);
			}
		}

		if (isset($data->metaData)) {
			$game->setMeta($data->metaData);
		}

		/** @phpstan-ignore return.type */
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
			          'id_arena = %i AND (start = %dt OR start = %dt)',
					  $this->arena->id,
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
			$this->getLogger()->error('Failed to save game', ['game' => $this]);
			return false;
		}

		$this->calculateSkills();

		foreach ($this->teams as $team) {
			$success &= $team->save();
		}
		if (!$success) {
			$this->getLogger()->error('Failed to save game teams', ['game' => $this]);
			return false;
		}
		if ($this->teams->count() === 0) {
			/** @var Player $player */
			foreach ($this->players as $player) {
				$success = $success && $player->save();
			}
		}

		if (!$success) {
			$this->getLogger()->error('Failed to save game players', ['game' => $this]);
		}

		if ($this->getGroup() !== null) {
			$success = $success && $this->getGroup()->save();
		}

		if (!$success) {
			$this->getLogger()->error('Failed to save game group', ['game' => $this]);
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

	public function update(): bool {
		$this->getLogger()->debug(json_encode(new \Exception()->getTrace()), ['group' => $this->group]);
		return parent::update();
	}

	#[AfterUpdate, AfterInsert, AfterDelete]
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

		$this->group?->clearCache();

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
		foreach ($this->players as $player) {
			$sum += $player->deaths;
		}
		return $sum / $this->playerCount;
	}

	public function getAverageHits(): float {
		$sum = 0;
		foreach ($this->players as $player) {
			$sum += $player->hits;
		}
		return $sum / $this->playerCount;
	}

	public function generatePhotosSecret() : string {
		if (!empty($this->photosSecret)) {
			return $this->photosSecret;
		}

		$this->photosSecret = rtrim(base64_encode(new Randomizer()->getBytes(32)), '=');
		bdump($this->photosSecret);
		return $this->photosSecret;
	}

	public function getProbableGameType() : GameModeType {
		if ($this->gameType === GameModeType::TEAM) {
			// If all players are in the same team or if each player is in a different team, it is a solo game
			if ($this->teams->count() < 2) {
				return GameModeType::SOLO;
			}
			foreach ($this->teams as $team) {
				if ($team->players->count() > 1) {
					return GameModeType::TEAM;
				}
			}
			return GameModeType::SOLO;
		}
		return $this->gameType;
	}

}
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
use App\Models\Auth\LigaPlayer;
use App\Models\GameGroup;
use App\Models\MusicMode;
use App\Models\Tournament\Game as TournamentGame;
use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Dibi\Row;
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
use Throwable;

if (!class_exists(Game::class)) {

	/**
	 * Base class for game models
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
	abstract class Game extends Model
	{
		/** @phpstan-use WithPlayers<P> */
		use WithPlayers;

		/** @phpstan-use WithTeams<T> */
		use WithTeams;

		public const SYSTEM     = '';
		public const CACHE_TAGS = ['games'];

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
		public ?Arena        $arena    = null;

		#[ManyToOne]
		public ?MusicMode $music = null;
		#[ManyToOne]
		public ?GameGroup $group = null;

		#[NoDB]
		public bool     $started  = false;
		#[NoDB]
		public bool     $finished = false;
		public bool     $visited  = false;
		protected float $realGameLength;

		#[NoDB]
		public ?TournamentGame $tournamentGame = null;

		public function __construct(?int $id = null, ?Row $dbRow = null) {
			$this->cacheTags[] = 'games/' . $this::SYSTEM;
			parent::__construct($id, $dbRow);
			$this->playerCount = $this->getPlayers()->count();
			if (isset($this->id) && !isset($this->mode)) {
				try {
					$this->getMode();
				} catch (GameModeNotFoundException) {
				}
			}
		}

		/**
		 * @return AbstractMode|null
		 * @throws GameModeNotFoundException
		 */
		public function getMode(): ?AbstractMode {
			if (!isset($this->mode) && isset($this->modeName)) {
				$this->mode = GameModeFactory::find($this->modeName, $this->gameType, $this::SYSTEM);
			}
			return $this->mode;
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
		 *     start?: array{date:string,timezone:string},
		 *     end?: array{date:string,timezone:string},
		 *     timing?: array<string,int>,
		 *     scoring?: array<string,int>,
		 *     mode?: array{type?:string,name:string},
		 *     players?: array{
		 *         id?: int,
		 *         id_player?: int,
		 *         name?: string,
		 *         code?: string,
		 *         team?: int,
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
						$game->mode = GameModeFactory::findByName(
							$value['name'],
							GameModeType::tryFrom(
								$value['type']
							) ?? GameModeType::TEAM,
							static::SYSTEM
						);
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
									case 'teamNum':
										/* @phpstan-ignore-next-line */
										$player->{$keyPlayer} = $valuePlayer;
										break;
									case 'bonus':
										/* @phpstan-ignore-next-line */
										$player->bonus = new BonusCounts(...$valuePlayer);
										break;
									case 'code':
										$player->user = LigaPlayer::getByCode($valuePlayer);
										break;
									case 'tournamentPlayer':
										if (((int)$valuePlayer) > 0) {
											$player->tournamentPlayer = \App\Models\Tournament\Player::get(
												(int)$valuePlayer
											);
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
											$team->tournamentTeam = \App\Models\Tournament\Team::get((int)$valueTeam);
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
				$game->mode = GameModeFactory::find($game->modeName, $game->gameType, $game::SYSTEM);
			}

			// Assign hits and teams
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
				$teamId = (int)($playerData['team'] ?? 0);
				if (isset($teams[$teamId])) {
					$player->setTeam($teams[$teamId]);
					$teams[$teamId]->addPlayer($player);
				}
			}
			return $game;
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
		 * @throws ModelNotFoundException
		 * @throws ValidationException
		 * @noinspection PhpMissingBreakStatementInspection
		 */
		public function getBestPlayer(string $property): ?Player {
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
		public function getBestsFields(): array {
			$fields = [
				'hits'     => lang('Největší terminátor', context: 'results.bests'),
				'deaths'   => lang('Objekt největšího zájmu', context: 'results.bests'),
				'score'    => lang('Absolutní vítěz', context: 'results.bests'),
				'accuracy' => lang('Hráč s nejlepší muškou', context: 'results.bests'),
				'shots'    => lang('Nejúspornější střelec', context: 'results.bests'),
				'miss'     => lang('Největší mimoň', context: 'results.bests'),
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
			return $this->getPlayers()->query()->filter('vest', $vestNum)->first();
		}

		/**
		 * @return array<string, mixed>
		 * @throws DirectoryCreationException
		 * @throws GameModeNotFoundException
		 * @throws ModelNotFoundException
		 * @throws ValidationException
		 */
		public function jsonSerialize(): array {
			$this->getTeams();
			$this->getPlayers();
			$data = parent::jsonSerialize();
			$data['players'] = $this->getPlayers()->getAll();
			$data['teams'] = $this->getTeams()->getAll();
			$data['group'] = $this->group;
			if (isset($data['tournamentGame'])) {
				unset($data['tournamentGame']);
			}
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
				          $this->start->getTimestamp() + ($this->timing?->before ?? 20)
			          )
			          ->fetch(cache: false);
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

			if (isset($this->group)) {
				$success = $success && $this->group->save();
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
		 * @throws DirectoryCreationException
		 * @throws ModelNotFoundException
		 * @throws ValidationException
		 */
		public function calculateSkills(): void {
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
			if (isset($this->group)) {
				$this->group->clearCache();
			}
			/** @var Cache $cache */
			$cache = App::getService('cache');
			$cache->clean([CacheParent::Tags => ['games/counts']]);
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
						'games/' . $this->code,
						'arena/' . $this->arena?->id . '/games/' . $this->start?->format('Y-m-d'),
					],
				]
			);

			if (isset($this->group)) {
				$this->group->clearCache();
			}

			// Invalidate generated results cache
			/** @var string[]|false $files */
			$files = glob(TMP_DIR . 'results/' . $this->code . '*');
			if ($files !== false) {
				foreach ($files as $file) {
					@unlink($file);
				}
			}
		}

		public function delete(): bool {
			/** @var Cache $cache */
			$cache = App::getService('cache');
			$cache->clean([CacheParent::Tags => ['games/counts']]);
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
			/** @var float[] $kds */
			$kds = $this->getPlayers()->query()->map(fn(Player $player) => $player->getKd())->get();
			return empty($kds) ? 1 : array_sum($kds) / count($kds);
		}

		public function recalculateScores(): void {
			if (isset($this->mode)) {
				$this->mode->recalculateScores($this);
				$this->reorder();
			}
		}

		public function reorder(): void {
			if (isset($this->mode)) {
				$this->mode->reorderGame($this);
			}
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

	}
}
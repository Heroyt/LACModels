<?php

namespace App\GameModels\Game;

use App\Core\AbstractModel;
use App\Core\DB;
use App\Core\Interfaces\InsertExtendInterface;
use App\Exceptions\GameModeNotFoundException;
use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\Enums\GameModeType;
use App\GameModels\Game\Evo5\BonusCounts;
use App\GameModels\Game\GameModes\AbstractMode;
use App\GameModels\Traits\WithPlayers;
use App\GameModels\Traits\WithTeams;
use App\Models\Arena;
use App\Services\Timer;
use App\Tools\Strings;
use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Dibi\Row;

abstract class Game extends AbstractModel implements InsertExtendInterface
{
	use WithPlayers;
	use WithTeams;

	public const SYSTEM      = '';
	public const PRIMARY_KEY = 'id_game';
	public const DEFINITION  = [
		'fileTime' => ['noTest' => true],
		'start'    => [],
		'end'      => [],
		'timing'   => [
			'validators' => ['instanceOf:'.Timing::class],
			'class'      => Timing::class,
		],
		'code'     => [
			'validators' => [],
		],
		'mode'     => [
			'validators' => ['instanceOf:'.AbstractMode::class],
			'class'      => AbstractMode::class,
		],
		'scoring'  => [
			'validators' => ['instanceOf:'.Scoring::class],
			'class'      => Scoring::class,
		],
		'arena'  => [
			'class' => Arena::class
		],
	];

	public int                $id_game;
	public ?DateTimeInterface $fileTime   = null;
	public ?DateTimeInterface $start      = null;
	public ?DateTimeInterface $importTime = null;
	public ?DateTimeInterface $end        = null;
	public ?Timing            $timing     = null;
	public string             $code;
	public ?AbstractMode      $mode       = null;
	public ?Scoring           $scoring    = null;
	public ?Arena             $arena      = null;

	public bool $started  = false;
	public bool $finished = false;

	public static function parseRow(Row $row) : ?InsertExtendInterface {
		if (isset($row->id_game, static::$instances[static::TABLE][$row->id_game])) {
			return static::$instances[static::TABLE][$row->id_game];
		}
		return null;
	}

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
				case 'lives':
				case 'ammo':
				case 'modeName':
				case 'fileNumber':
				case 'code':
				case 'respawn':
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
					$game->mode = GameModeFactory::findByName($value['name'], GameModeType::from($value['type']) ?? GameModeType::TEAM, static::SYSTEM);
					break;
				case 'players':
				{
					foreach ($value as $playerNum => $playerData) {
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

	public function save() : bool {
		Timer::start('game.check');
		$pk = $this::PRIMARY_KEY;
		/** @var object{id_game:int,code:string|null}|null $test */
		$test = DB::select($this::TABLE, $pk.', code')->where('start = %dt', $this->start)->fetch();
		if (isset($test)) {
			$this->id = $test->$pk;
			$this->code = $test->code;
		}
		if (empty($this->code)) {
			$this->code = uniqid('g', false);
		}
		Timer::stop('game.check');
		return parent::save();
	}

	public function addQueryData(array &$data) : void {
		$data[$this::PRIMARY_KEY] = $this->id;
	}

	public function isStarted() : bool {
		return !is_null($this->start);
	}

	public function isFinished() : bool {
		return !is_null($this->end);
	}

	/**
	 * @param string $property
	 *
	 * @return Player|null
	 */
	public function getBestPlayer(string $property) : ?Player {
		$query = $this->getPlayers()->query()->sortBy($property);
		switch ($property) {
			case 'shots':
				$query->asc();
				break;
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
	 */
	public function getVestPlayer(int $vestNum) : ?Player {
		return $this->getPlayers()->query()->filter('vest', $vestNum)->first();
	}

	public function jsonSerialize() : array {
		$data = parent::jsonSerialize();
		$data['players'] = $this->getPlayers()->getAll();
		$data['teams'] = $this->getTeams()->getAll();
		return $data;
	}

}
<?php

namespace App\GameModels\Factory;

use App\GameModels\Game\Game;
use App\Tools\Strings;
use DateTime;
use Dibi\Fluent;
use InvalidArgumentException;
use Lsr\Core\App;
use Lsr\Core\Caching\Cache;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Models\Interfaces\FactoryInterface;
use Lsr\Helpers\Tools\Timer;
use Throwable;

class GameFactory implements FactoryInterface
{

	/**
	 * Get game by its unique code
	 *
	 * @param string $code
	 *
	 * @return Game|null
	 * @throws Throwable
	 */
	public static function getByCode(string $code) : ?Game {
		Timer::startIncrementing('factory.game');
		/** @var Cache $cache */
		$cache = App::getService('cache');
		$game = $cache->load('games/'.$code, static function(array &$dependencies) use ($code) {
			$dependencies[Cache::EXPIRE] = '7 days';
			$dependencies[Cache::Tags] = [
				'games',
				'models',
			];
			$gameRow = self::queryGames()->where('[code] = %s', $code)->fetch();
			if (isset($gameRow)) {
				$game = self::getById($gameRow->id_game, ['system' => $gameRow->system]);
				$dependencies[Cache::Tags][] = 'games/'.$game::SYSTEM;
				if (isset($game)) {
					$dependencies[Cache::Tags][] = 'games/'.$game::SYSTEM.'/'.$game->id;
				}
				return $game;
			}
			return null;
		});
		Timer::stop('factory.game');
		return $game;
	}

	/**
	 * Prepare a SQL query for all games (from all systems)
	 *
	 * @param bool          $excludeNotFinished
	 * @param DateTime|null $date
	 *
	 * @return Fluent
	 */
	public static function queryGames(bool $excludeNotFinished = false, ?DateTime $date = null) : Fluent {
		$query = DB::getConnection()->select('*');
		$queries = [];
		foreach (self::getSupportedSystems() as $key => $system) {
			$q = DB::select(["[{$system}_games]", "[g$key]"], "[g$key].[id_game], %s as [system], [g$key].[code], [g$key].[start], [g$key].[end], [g$key].[sync]", $system);
			if ($excludeNotFinished) {
				$q->where("[g$key].[end] IS NOT NULL");
			}
			if (isset($date)) {
				$q->where("DATE([g$key].[start]) = %d", $date);
			}
			$queries[] = (string) $q;
		}
		$query->from('%sql', '(('.implode(') UNION ALL (', $queries).')) [t]');
		return $query;
	}

	/**
	 * Get a list of all supported systems
	 *
	 * @return string[]
	 */
	public static function getSupportedSystems() : array {
		return require ROOT.'config/supportedSystems.php';
	}

	/**
	 * Get a game model
	 *
	 * @param int                  $id
	 * @param array{system:string} $options
	 *
	 * @return Game|null
	 * @throws Throwable
	 */
	public static function getById(int $id, array $options = []) : ?Game {
		$system = $options['system'] ?? '';
		if (empty($system)) {
			throw new InvalidArgumentException('System name is required.');
		}
		Timer::startIncrementing('factory.game');
		try {
			/** @var Cache $cache */
			$cache = App::getService('cache');
			$game = $cache->load('games/'.$system.'/'.$id, function(array &$dependencies) use ($system, $id) {
				$dependencies[Cache::EXPIRE] = '7 days';
				$dependencies[Cache::Tags] = [
					'models',
					'games',
					'system/'.$system,
					'games/'.$system,
					'games/'.$system.'/'.$id,
				];
				/** @var Game|string $className */
				$className = '\\App\\GameModels\\Game\\'.Strings::toPascalCase($system).'\\Game';
				if (!class_exists($className)) {
					throw new InvalidArgumentException('Game model of does not exist: '.$className);
				}
				return $className::get($id);
			});
		} catch (ModelNotFoundException $e) {
			Timer::stop('factory.game');
			return null;
		}
		Timer::stop('factory.game');
		return $game;
	}

	/**
	 * Get the last played game
	 *
	 * @param string $system             System filter
	 * @param bool   $excludeNotFinished By default, filter unfinished games
	 *
	 * @return Game|null
	 * @throws Throwable
	 */
	public static function getLastGame(string $system = 'all', bool $excludeNotFinished = true) : ?Game {
		if ($system === 'all') {
			$query = self::queryGames(true);
		}
		else {
			$query = self::queryGamesSystem($system, $excludeNotFinished);
		}
		$row = $query->orderBy('end')->desc()->fetch();
		if (isset($row)) {
			return self::getById($row->id_game, ['system' => $row->system]);
		}
		return null;
	}

	/**
	 * Prepare a SQL query for all games (from one system)
	 *
	 * @param string $system
	 * @param bool   $excludeNotFinished
	 *
	 * @return Fluent
	 */
	public static function queryGamesSystem(string $system, bool $excludeNotFinished = false) : Fluent {
		$query = DB::select(["[{$system}_games]"], "[id_game], %s as [system], [code], [start], [end]", $system);
		if ($excludeNotFinished) {
			$query->where("[end] IS NOT NULL");
		}
		return $query;
	}

	/**
	 * Get games for the day
	 *
	 * @param DateTime $date
	 * @param bool     $excludeNotFinished
	 *
	 * @return Game[]
	 * @throws Throwable
	 */
	public static function getByDate(DateTime $date, bool $excludeNotFinished = false) : array {
		Timer::startIncrementing('factory.game');
		/** @var Cache $cache */
		$cache = App::getService('cache');
		$games = $cache->load('games/'.$date->format('Y-m-d').($excludeNotFinished ? '/finished' : ''), static function(array &$dependencies) use ($date, $excludeNotFinished) {
			$dependencies[Cache::EXPIRE] = '7 days';
			$dependencies[Cache::Tags] = [
				'games',
				'models',
				'games/'.$date->format('Y-m-d'),
			];
			$games = [];
			$query = self::queryGames($excludeNotFinished)->where('DATE([start]) = %d', $date)->orderBy('start')->desc();
			$rows = $query->fetchAll();
			foreach ($rows as $row) {
				$game = self::getById($row->id_game, ['system' => $row->system]);
				if (isset($game)) {
					$games[] = $game;
				}
			}
			return $games;
		});
		Timer::stop('factory.game');
		return $games;
	}

	/**
	 * Get game counts for each dates
	 *
	 * @param string $format
	 * @param bool   $excludeNotFinished
	 *
	 * @return array<string,int>
	 */
	public static function getGamesCountPerDay(string $format = 'Y-m-d', bool $excludeNotFinished = false) : array {
		$rows = self::queryGameCountPerDay($excludeNotFinished)->fetchAll();
		$return = [];
		foreach ($rows as $row) {
			if (!isset($row->date)) {
				continue;
			}
			$return[$row->date->format($format)] = $row->count;
		}
		return $return;
	}

	/**
	 * @param bool $excludeNotFinished
	 *
	 * @return Fluent
	 */
	public static function queryGameCountPerDay(bool $excludeNotFinished = false) : Fluent {
		$query = DB::getConnection()->select('[date], count(*) as [count]');
		$queries = [];
		foreach (self::getSupportedSystems() as $key => $system) {
			$q = DB::select(["[{$system}_games]", "[g$key]"], "[g$key].[code], DATE([g$key].[start]) as [date]");
			if ($excludeNotFinished) {
				$q->where("[g$key].[end] IS NOT NULL");
			}
			$queries[] = (string) $q;
		}
		$query
			->from('%sql', '(('.implode(') UNION ALL (', $queries).')) [t]')
			->groupBy('date');
		return $query;
	}

	/**
	 * Get team colors for all supported systems
	 *
	 * @return string[][]
	 */
	public static function getAllTeamsColors() : array {
		$colors = [];
		foreach (self::getSupportedSystems() as $system) {
			/** @var Game $className */
			$className = 'App\GameModels\Game\\'.ucfirst($system).'\Game';
			if (method_exists($className, 'getTeamColors')) {
				$colors[$system] = $className::getTeamColors();
			}
		}
		return $colors;
	}

	/**
	 * @param array{system:string|null, excludeNotFinished: bool|null} $options
	 *
	 * @return Game[]
	 * @throws Throwable
	 */
	public static function getAll(array $options = []) : array {
		if (!empty($options['system'])) {
			$rows = self::queryGamesSystem($options['system'], isset($options['excludeNotFinished']) && $options['excludeNotFinished'])->fetchAll();
		}
		else {
			$rows = self::queryGames(isset($options['excludeNotFinished']) && $options['excludeNotFinished'])->fetchAll();
		}
		$models = [];
		foreach ($rows as $row) {
			$models[] = self::getById($row->id_game, ['system' => $row->system]);
		}
		return $models;
	}
}
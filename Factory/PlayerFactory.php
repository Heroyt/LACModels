<?php

namespace App\GameModels\Factory;

use App\GameModels\Game\Player;
use Dibi\Fluent;
use InvalidArgumentException;
use Lsr\Core\App;
use Lsr\Core\Caching\Cache;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Models\Interfaces\FactoryInterface;
use Lsr\Helpers\Tools\Strings;
use Lsr\Helpers\Tools\Timer;
use Nette\Caching\Cache as CacheBase;
use Throwable;

/**
 * Factory for game models
 *
 * Works with multiple different laser game systems.
 *
 * @implements FactoryInterface<Player>
 */
class PlayerFactory implements FactoryInterface
{

	/**
	 * @param array{system?:string} $options
	 *
	 * @return Player[]
	 * @throws Throwable
	 */
	public static function getAll(array $options = []) : array {
		if (!empty($options['system'])) {
			$rows = self::queryPlayersSystem($options['system'])->fetchAll();
		}
		else {
			$rows = self::queryPlayers()->fetchAll();
		}
		$models = [];
		foreach ($rows as $row) {
			$model = self::getById($row->id_team, ['system' => $row->system]);
			if (isset($model)) {
				$models[] = $model;
			}
		}
		return $models;
	}

	/**
	 * Prepare a SQL query for all players
	 *
	 * @param string  $system
	 * @param int[][] $gameIds
	 *
	 * @return Fluent
	 */
	public static function queryPlayersSystem(string $system, array $gameIds = []) : Fluent {
		$q = DB::select(["[{$system}_players]", "[g]"], "[g].[id_player], [g].[id_game], [g].[id_team], %s as [system], [g].[name], [g].[score], [g].[accuracy], [g].[hits], [g].[deaths], [g].[shots]", $system);
		if (!empty($gameIds)) {
			$q->where("[g].[id_game] IN %in", $gameIds);
		}
		return $q;
	}

	/**
	 * Prepare a SQL query for all players (from all systems)
	 *
	 * @param int[][] $gameIds
	 *
	 * @return Fluent
	 */
	public static function queryPlayers(array $gameIds = []) : Fluent {
		$query = DB::getConnection()->select('*');
		$queries = [];
		foreach (GameFactory::getSupportedSystems() as $key => $system) {
			$q = DB::select(["[{$system}_players]", "[g$key]"], "[g$key].[id_player], [g$key].[id_game], [g$key].[id_team], %s as [system], [g$key].[name], [g$key].[score], [g$key].[accuracy], [g$key].[hits], [g$key].[deaths], [g$key].[shots]", $system);
			if (!empty($gameIds[$system])) {
				$q->where("[g$key].[id_game] IN %in", $gameIds[$system]);
			}
			$queries[] = (string) $q;
		}
		/** @noinspection PhpParamsInspection */
		$query->from('%sql', '(('.implode(') UNION ALL (', $queries).')) [t]');
		return $query;
	}

	/**
	 * Get a game model
	 *
	 * @param int                   $id
	 * @param array{system?:string} $options
	 *
	 * @return Player|null
	 * @throws Throwable
	 */
	public static function getById(int $id, array $options = []) : ?Player {
		$system = $options['system'] ?? '';
		if (empty($system)) {
			throw new InvalidArgumentException('System name is required.');
		}
		Timer::startIncrementing('factory.player');
		try {
			/** @var Cache $cache */
			$cache = App::getService('cache');
			/** @var Player|null $player */
			$player = $cache->load('players/'.$system.'/'.$id, function(array &$dependencies) use ($system, $id) {
				$dependencies[CacheBase::EXPIRE] = '7 days';
				/** @var class-string<Player> $className */
				$className = '\\App\\GameModels\\Game\\'.Strings::toPascalCase($system).'\\Player';
				if (!class_exists($className)) {
					throw new InvalidArgumentException('Player model of does not exist: '.$className);
				}
				$player = $className::get($id);
				$dependencies[CacheBase::Tags] = [
					'models',
					'players',
					'system/'.$system,
					'players/'.$system,
					'games/'.$system.'/'.$player->getGame()->id,
				];
				return $player;
			});
		} catch (ModelNotFoundException $e) {
			Timer::stop('factory.player');
			bdump($e);
			return null;
		}
		Timer::stop('factory.player');
		return $player;
	}
}
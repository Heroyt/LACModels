<?php

namespace App\GameModels\Factory;

use App\GameModels\Game\Team;
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
use RuntimeException;
use Throwable;

/**
 * Factory for team models
 *
 * Works with multiple different laser game systems.
 *
 * @implements FactoryInterface<Team>
 */
class TeamFactory implements FactoryInterface
{

	/**
	 * @param array{system?:string} $options
	 *
	 * @return Team[]
	 * @throws Throwable
	 */
	public static function getAll(array $options = []) : array {
		if (!empty($options['system'])) {
			$rows = self::queryTeamsSystem($options['system'])->fetchAll();
		}
		else {
			$rows = self::queryTeams()->fetchAll();
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
	 * Prepare a SQL query for all teams
	 *
	 * @param string  $system
	 * @param int[][] $gameIds
	 *
	 * @return Fluent
	 */
	public static function queryTeamsSystem(string $system, array $gameIds = []) : Fluent {
		$q = DB::select(["[{$system}_teams]", "[g]"], "[g].[id_team], [g].[id_game], [g].[color], %s as [system], [g].[name], [g].[score]", $system);
		if (!empty($gameIds)) {
			$q->where("[g].[id_game] IN %in", $gameIds);
		}
		return $q;
	}

	/**
	 * Prepare a SQL query for all teams (from all systems)
	 *
	 * @param int[][] $gameIds
	 *
	 * @return Fluent
	 */
	public static function queryTeams(array $gameIds = []) : Fluent {
		$query = DB::getConnection()->select('*');
		$queries = [];
		foreach (GameFactory::getSupportedSystems() as $key => $system) {
			$q = DB::select(["[{$system}_teams]", "[g$key]"], "[g$key].[id_team], [g$key].[id_game], [g$key].[color], %s as [system], [g$key].[name], [g$key].[score]", $system);
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
	 * @return Team|null
	 * @throws Throwable
	 */
	public static function getById(int $id, array $options = []) : ?Team {
		$system = $options['system'] ?? '';
		if (empty($system)) {
			throw new InvalidArgumentException('System name is required.');
		}
		Timer::startIncrementing('factory.team');
		try {
			/** @var Cache $cache */
			$cache = App::getService('cache');
			/** @var Team|null $team */
			$team = $cache->load('teams/'.$system.'/'.$id, function(array &$dependencies) use ($system, $id) {
				$dependencies[CacheBase::EXPIRE] = '7 days';
				/** @var class-string<Team> $className */
				$className = '\\App\\GameModels\\Game\\'.Strings::toPascalCase($system).'\\Team';
				if (!class_exists($className)) {
					throw new InvalidArgumentException('Team model of does not exist: '.$className);
				}
				$team = $className::get($id);
				$dependencies[CacheBase::Tags] = [
					'models',
					'teams',
					'system/'.$system,
					'teams/'.$system,
					'games/'.$team->getGame()->code,
					'games/'.$system.'/'.$team->getGame()->id,
				];
				try {
					$dependencies[CacheBase::Tags][] = 'games/'.$system.'/'.$team->getGame()->id;
				} catch (RuntimeException) {
				}
				return $team;
			});

		} catch (ModelNotFoundException) {
			Timer::stop('factory.team');
			return null;
		}
		Timer::stop('factory.team');
		return $team;
	}

}
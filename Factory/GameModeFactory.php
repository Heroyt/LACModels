<?php

namespace App\GameModels\Factory;

use App\Exceptions\GameModeNotFoundException;
use App\GameModels\Game\Enums\GameModeType;
use App\GameModels\Game\GameModes\AbstractMode;
use Dibi\Row;
use Lsr\Core\DB;
use Lsr\Core\Models\Interfaces\FactoryInterface;
use Lsr\Helpers\Tools\Timer;
use Nette\Utils\Strings;

/**
 * Factory for game mode models
 *
 * Works with multiple different laser game systems.
 *
 * @implements FactoryInterface<AbstractMode>
 */
class GameModeFactory implements FactoryInterface
{

	/**
	 * @param int                   $id
	 * @param array{system?:string} $options
	 *
	 * @return AbstractMode|null
	 * @throws GameModeNotFoundException
	 */
	public static function getById(int $id, array $options = []): ?AbstractMode {
		/** @var Row|null $mode */
		$mode = DB::select('game_modes', 'id_mode, name, system, type')->where('id_mode = %i', $id)->fetch();
		$system = (string)($mode->system ?? '');
		$modeType = GameModeType::tryFrom((string)($mode->type ?? 'TEAM')) ?? GameModeType::TEAM;
		return self::findModeObject($system, $mode, $modeType);
	}

	/**
	 * @param string       $system
	 * @param Row|null     $mode
	 * @param GameModeType $modeType
	 *
	 * @return AbstractMode
	 * @throws GameModeNotFoundException
	 * @noinspection PhpUndefinedFieldInspection
	 */
	protected static function findModeObject(string $system, ?Row $mode, GameModeType $modeType): AbstractMode {
		Timer::startIncrementing('factory.gamemode');
		$args = [];
		$classBase = 'App\\GameModels\\Game\\';
		$classSystem = '';
		if (!empty($system)) {
			$classSystem = Strings::firstUpper($system) . '\\';
		}
		$classNamespace = 'GameModes\\';
		$className = '';
		if (isset($mode)) {
			if (is_numeric($mode->name[0])) {
				$mode->name = 'M' . $mode->name;
			}
			$dbName = str_replace([' ', '.', '_', '-'], '', Strings::toAscii(Strings::capitalize($mode->name)));
			$class = $classBase . $classSystem . $classNamespace . $dbName;
			$args[] = $mode->id_mode;
			if (class_exists($class)) {
				$className = $dbName;
			}
			else if (class_exists($classBase . $classSystem . $classNamespace . strtoupper($dbName))) {
				$className = strtoupper($dbName);
			}
			else if ($modeType === GameModeType::TEAM) {
				$classSystem = '';
				$className = 'CustomTeamMode';
			}
			else {
				$classSystem = '';
				$className = 'CustomSoloMode';
			}
		}

		if (empty($className)) {
			if ($modeType === GameModeType::TEAM) {
				$className = 'TeamDeathmach';
			}
			else {
				$className = 'Deathmach';
			}
		}
		$class = $classBase . $classSystem . $classNamespace . $className;
		if (!class_exists($class)) {
			$class = $classBase . $classNamespace . $className;
		}
		if (!class_exists($class)) {
			throw new GameModeNotFoundException(
				'Cannot find game mode class: ' . (isset($dbName) ? $classBase . $classSystem . $classNamespace . $dbName . '|' : '') . $classBase . $classSystem . $classNamespace . $className . '|' . $classBase . $classNamespace . $className
			);
		}
		/** @var AbstractMode $gameMode */
		$gameMode = new $class(...$args);

		if (!isset($gameMode->id)) {
			$gameMode = self::findByName($gameMode->getName(), $gameMode->type, $system);
		}

		Timer::stop('factory.gamemode');
		return $gameMode;
	}

	/**
	 * @param string       $modeName Raw game mode name
	 * @param GameModeType $modeType Mode type: 0 = Solo, 1 = Team
	 * @param string       $system   System name
	 *
	 * @return AbstractMode
	 * @throws GameModeNotFoundException
	 */
	public static function find(string $modeName, GameModeType $modeType = GameModeType::TEAM, string $system = ''): AbstractMode {
		/** @var Row|null $mode */
		$mode = DB::select('vModesNames', 'id_mode, name, system')->where(
			'%s LIKE CONCAT(\'%\', [sysName], \'%\')',
			$modeName
		)->fetch();
		if (isset($mode->system)) {
			$system = $mode->system;
		}
		return self::findModeObject($system, $mode, $modeType);
	}

	/**
	 * @param string       $modeName Raw game mode name
	 * @param GameModeType $modeType Mode type: 0 = Solo, 1 = Team
	 * @param string       $system   System name
	 *
	 * @return AbstractMode
	 * @throws GameModeNotFoundException
	 */
	public static function findByName(string $modeName, GameModeType $modeType = GameModeType::TEAM, string $system = ''): AbstractMode {
		/** @var Row|null $mode */
		$mode = DB::select('vModesNames', 'id_mode, name, system')->where('[name] = %s', $modeName)->fetch();
		if (isset($mode->system)) {
			$system = $mode->system;
		}
		return self::findModeObject($system, $mode, $modeType);
	}

	/**
	 * @param class-string<AbstractMode>|object $object
	 *
	 * @return int
	 * @throws GameModeNotFoundException
	 */
	public static function getIdByObject(string|object $object): int {
		$modes = self::getAll();
		foreach ($modes as $mode) {
			if ($mode instanceof $object && isset($mode->id)) {
				return $mode->id;
			}
		}
		return 0;
	}

	/**
	 * @param array{system?:string,rankable?:bool,all?:bool} $options
	 *
	 * @return AbstractMode[]
	 * @throws GameModeNotFoundException
	 */
	public static function getAll(array $options = []): array {
		$ids = DB::select('game_modes', 'id_mode, name, system, type')
		         ->cacheTags(AbstractMode::TABLE . '/query');
		if (isset($options['system'])) {
			$ids->where('system = %s', $options['system']);
		}
		if (isset($options['rankable'])) {
			$ids->where('rankable = %i', $options['rankable'] ? 1 : 0);
		}
		if (!isset($options['all']) || !((bool)$options['all'])) {
			$ids->where('active = 1');
		}
		$ids = $ids->fetchAssoc('id_mode');
		$modes = [];
		foreach ($ids as $mode) {
			$system = $mode->system ?? '';
			$modeType = GameModeType::tryFrom($mode->type ?? 'TEAM') ?? GameModeType::TEAM;
			$modes[] = self::findModeObject($system, $mode, $modeType);
		}
		return $modes;
	}

}
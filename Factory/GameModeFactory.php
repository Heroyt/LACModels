<?php

namespace App\GameModels\Factory;

use App\Exceptions\GameModeNotFoundException;
use App\GameModels\Game\Enums\GameModeType;
use App\GameModels\Game\GameModes\AbstractMode;
use Dibi\Row;
use Lsr\Core\DB;
use Lsr\Core\Models\Interfaces\FactoryInterface;
use Nette\Utils\Strings;

class GameModeFactory implements FactoryInterface
{

	/**
	 * @return AbstractMode[]
	 * @throws GameModeNotFoundException
	 */
	public static function getAll(array $options = []) : array {
		$ids = DB::select('game_modes', 'id_mode, name, system, type')->fetchAssoc('id_mode');
		$modes = [];
		foreach ($ids as $id => $mode) {
			$system = $mode->system ?? '';
			$modeType = GameModeType::tryFrom($mode->type ?? 'TEAM') ?? GameModeType::TEAM;
			$modes[] = self::findModeObject($system, $mode, $modeType);
		}
		return $modes;
	}

	/**
	 * @param string         $system
	 * @param array|Row|null $mode
	 * @param GameModeType   $modeType
	 *
	 * @return mixed
	 * @throws GameModeNotFoundException
	 */
	protected static function findModeObject(string $system, array|Row|null $mode, GameModeType $modeType) : mixed {
		$args = [];
		$classBase = 'App\\GameModels\\Game\\';
		$classSystem = '';
		if (!empty($system)) {
			$classSystem = Strings::firstUpper($system).'\\';
		}
		$classNamespace = 'GameModes\\';
		$className = '';
		if (isset($mode)) {
			if (is_numeric($mode->name[0])) {
				$mode->name = 'M'.$mode->name;
			}
			$dbName = str_replace([' ', '.', '_', '-'], '', Strings::toAscii(Strings::capitalize($mode->name)));
			$class = $classBase.$classSystem.$classNamespace.$dbName;
			$args[] = $mode->id_mode;
			if (class_exists($class)) {
				$className = $dbName;
			}
			else if (class_exists($classBase.$classSystem.$classNamespace.strtoupper($dbName))) {
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
		$class = $classBase.$classSystem.$classNamespace.$className;
		if (!class_exists($class)) {
			$class = $classBase.$classNamespace.$className;
		}
		if (!class_exists($class)) {
			throw new GameModeNotFoundException('Cannot find game mode class: '.(isset($dbName) ? $classBase.$classSystem.$classNamespace.$dbName.'|' : '').$classBase.$classSystem.$classNamespace.$className.'|'.$classBase.$classNamespace.$className);
		}
		return new $class(...$args);
	}

	/**
	 * @param int   $id
	 * @param array $options
	 *
	 * @return AbstractMode|null
	 * @throws GameModeNotFoundException
	 */
	public static function getById(int $id, array $options = []) : ?AbstractMode {
		$mode = DB::select('game_modes', 'id_mode, name, system, type')->where('id_mode = %i', $id)->fetch();
		$system = $mode->system ?? '';
		$modeType = GameModeType::tryFrom($mode->type ?? 'TEAM') ?? GameModeType::TEAM;
		return self::findModeObject($system, $mode, $modeType);
	}

	/**
	 * @param string $modeName Raw game mode name
	 * @param int    $modeType Mode type: 0 = Solo, 1 = Team
	 * @param string $system   System name
	 *
	 * @return AbstractMode
	 * @throws GameModeNotFoundException
	 */
	public static function find(string $modeName, GameModeType $modeType = GameModeType::TEAM, string $system = '') : AbstractMode {
		$mode = DB::select('vModesNames', 'id_mode, name, system')->where('%s LIKE CONCAT(\'%\', [sysName], \'%\')', $modeName)->fetch();
		if (isset($mode->system)) {
			/** @noinspection CallableParameterUseCaseInTypeContextInspection */
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
	public static function findByName(string $modeName, GameModeType $modeType = GameModeType::TEAM, string $system = '') : AbstractMode {
		$mode = DB::select('vModesNames', 'id_mode, name, system')->where('[name] = %s', $modeName)->fetch();
		if (isset($mode->system)) {
			/** @noinspection CallableParameterUseCaseInTypeContextInspection */
			$system = $mode->system;
		}
		return self::findModeObject($system, $mode, $modeType);
	}


}
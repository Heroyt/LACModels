<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game\Lasermaxx\GameModes;

use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\Game;
use App\GameModels\Game\GameModes\AbstractMode;
use App\GameModels\Game\GameModes\CustomizeAfterImport;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\PrimaryKey;

/**
 * Special LaserMaxx Evo5 game mode
 */
#[PrimaryKey('id_mode')]
#[Factory(GameModeFactory::class)] // @phpstan-ignore-line
abstract class Barvicky extends AbstractMode implements CustomizeAfterImport
{
    use LaserMaxxScores;


    public string $name = 'Barvičky';

	public function isSolo(): bool {
		return true;
	}

	public function isTeam(): bool {
		return false;
	}

	public function processImportedGame(Game $game): void {
		$game->recalculateScores();
	}
}

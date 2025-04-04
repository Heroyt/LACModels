<?php
declare(strict_types=1);

namespace App\GameModels\Game\GameModes;

use App\GameModels\Game\Game;

interface CustomizeAfterImport
{

	public function processImportedGame(Game $game): void;

}
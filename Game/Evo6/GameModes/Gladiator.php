<?php
declare(strict_types=1);

namespace App\GameModels\Game\Evo6\GameModes;

use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\Evo6\Game;
use App\GameModels\Game\Evo6\Player;
use App\GameModels\Game\GameModes\CustomPlayerResultsMode;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\PrimaryKey;

/** @phpstan-ignore argument.type */
#[PrimaryKey('id_mode'), Factory(GameModeFactory::class)]
class Gladiator extends Deathmatch implements CustomPlayerResultsMode
{
    public string $name = 'Gladiator';

	public function getCustomPlayerTemplate(): string {
		return 'pages/game/gameModes/gladiator/player';
	}

	public function getRespawns(Player $player): int {
		$game = $player->game;
		assert($game instanceof Game);

		$livesTotalGained = $game->lives+($player->hits * $game->hitGainSettings->lives)-$player->livesRest;
		$deathsAfterFirstRespawn = $player->deaths - $livesTotalGained;
		if ($deathsAfterFirstRespawn < 0) {
			return 0;
		}
		return (int) (1 + ceil($deathsAfterFirstRespawn / $game->respawnSettings->respawnLives));
	}
}
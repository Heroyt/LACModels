<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game\Evo5;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Evo5\GameModes\Deathmatch;
use App\GameModels\Game\Evo5\GameModes\TeamDeathmatch;
use App\GameModels\Game\GameModes\AbstractMode;
use Lsr\Lg\Results\Enums\GameModeType;
use Lsr\Lg\Results\LaserMaxx\Evo5\Evo5GameInterface;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\Instantiate;
use Lsr\Orm\Attributes\JsonExclude;
use Lsr\Orm\Attributes\NoDB;
use Lsr\Orm\Attributes\PrimaryKey;
use OpenApi\Attributes as OA;

/**
 * LaserMaxx Evo5 game model
 *
 * @extends \App\GameModels\Game\Lasermaxx\Game<Team, Player>
 * @phpstan-ignore-next-line
 */
#[PrimaryKey('id_game'), Factory(GameFactory::class, ['system' => 'evo5']), OA\Schema(schema: 'GameEvo5')]
class Game extends \App\GameModels\Game\Lasermaxx\Game implements Evo5GameInterface
{

	public const string SYSTEM = 'evo5';
	public const string TABLE  = 'evo5_games';
	protected const array IMPORT_PROPERTIES = [
		'resultsFile',
		'fileTime',
		'modeName',
		'importTime',
		'start',
		'end',
		'gameType',
		'code',
		'fileNumber',
		'lives',
		'ammo',
		'respawn',
		'scoring',
	];

	#[NoDB, JsonExclude]
	public string  $playerClass = Player::class;
	#[NoDB, JsonExclude]
	public string   $teamClass   = Team::class;
	#[Instantiate, OA\Property]
	public \Lsr\Lg\Results\LaserMaxx\Evo5\Scoring $scoring;

    public function loadMode() : AbstractMode {
        return parent::loadMode() ?? ($this->gameType === GameModeType::SOLO ? new Deathmatch() : new TeamDeathmatch());
    }

	public function getAverageTeammateDeaths(): float {
		$sum = 0;
		foreach ($this->getPlayers() as $player) {
			$sum += $player->deathsOwn;
		}
		return $sum / $this->getPlayerCount();
	}

	public function getAverageTeammateHits(): float {
		$sum = 0;
		foreach ($this->getPlayers() as $player) {
			$sum += $player->hitsOwn;
		}
		return $sum / $this->getPlayerCount();
	}
}

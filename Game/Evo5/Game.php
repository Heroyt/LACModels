<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game\Evo5;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Enums\GameModeType;
use App\GameModels\Game\Evo5\GameModes\Deathmach;
use App\GameModels\Game\Evo5\GameModes\TeamDeathmach;
use App\GameModels\Game\GameModes\AbstractMode;
use Lsr\Core\Models\Attributes\Factory;
use Lsr\Core\Models\Attributes\Instantiate;
use Lsr\Core\Models\Attributes\NoDB;
use Lsr\Core\Models\Attributes\PrimaryKey;
use OpenApi\Attributes as OA;

/**
 * LaserMaxx Evo5 game model
 *
 * @extends \App\GameModels\Game\Lasermaxx\Game<Team, Player>
 * @phpstan-ignore-next-line
 */
#[PrimaryKey('id_game'), Factory(GameFactory::class, ['system' => 'evo5']), OA\Schema(schema: 'GameEvo5')]
class Game extends \App\GameModels\Game\Lasermaxx\Game
{

	public const string SYSTEM = 'evo5';
	public const string TABLE  = 'evo5_games';

	#[NoDB]
	public string  $playerClass = Player::class;
	#[NoDB]
	public string   $teamClass   = Team::class;
	#[Instantiate]
	#[OA\Property]
	public Scoring $scoring;

	public function getMode(): ?AbstractMode {
		return parent::getMode() ?? ($this->gameType === GameModeType::SOLO ? new Deathmach() : new TeamDeathmach());
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
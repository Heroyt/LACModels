<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game\Lasermaxx\Evo5;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\GameModes\AbstractMode;
use App\GameModels\Game\Lasermaxx\Evo5\GameModes\Deathmatch;
use App\GameModels\Game\Lasermaxx\Evo5\GameModes\TeamDeathmatch;
use Lsr\Lg\Results\Enums\GameModeType;
use Lsr\Lg\Results\LaserMaxx\Evo5\Evo5GameInterface;
use Lsr\Lg\Results\LaserMaxx\Evo5\Scoring;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\Instantiate;
use Lsr\Orm\Attributes\JsonExclude;
use Lsr\Orm\Attributes\NoDB;
use Lsr\Orm\Attributes\PrimaryKey;

/**
 * LaserMaxx Evo5 game model
 *
 * @phpstan-import-type GameMeta from \App\GameModels\Game\Game
 *
 * @extends \App\GameModels\Game\Lasermaxx\Game<Team, Player>
 * @implements Evo5GameInterface<Team, Player, GameMeta>
 */
#[
  PrimaryKey('id_game'),
  Factory(GameFactory::class, ['system' => 'evo5']) // @phpstan-ignore argument.type
]
class Game extends \App\GameModels\Game\Lasermaxx\Game implements Evo5GameInterface
{
    public const string SYSTEM = 'evo5';
    public const string TABLE = 'evo5_games';

    #[NoDB, JsonExclude]
    public string $playerClass = Player::class;
    #[NoDB, JsonExclude]
    public string $teamClass = Team::class;
    #[Instantiate]
    public Scoring $scoring;

    public function loadMode() : AbstractMode {
        return parent::loadMode() ?? ($this->gameType === GameModeType::SOLO ? new Deathmatch() : new TeamDeathmatch());
    }
}

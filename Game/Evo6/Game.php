<?php

namespace App\GameModels\Game\Evo6;

use App\GameModels\Factory\GameFactory;
use Lsr\Core\Models\Attributes\Factory;
use Lsr\Core\Models\Attributes\Instantiate;
use Lsr\Core\Models\Attributes\NoDB;
use Lsr\Core\Models\Attributes\PrimaryKey;

/**
 * LaserMaxx Evo6 game model
 *
 * @extends \App\GameModels\Game\Lasermaxx\Game<Team, Player>
 * @phpstan-ignore-next-line
 */
#[PrimaryKey('id_game'), Factory(GameFactory::class, ['system' => 'evo6'])]
class Game extends \App\GameModels\Game\Lasermaxx\Game
{
    public const string SYSTEM = 'evo6';
    public const string TABLE  = 'evo6_games';

    #[NoDB]
    public string $playerClass = Player::class;
    #[NoDB]
    public string $teamClass   = Team::class;
    #[Instantiate]
    public Scoring $scoring;
}

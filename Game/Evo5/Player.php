<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game\Evo5;

use App\GameModels\Factory\PlayerFactory;
use App\GameModels\Game\Game as BaseGame;
use Lsr\Core\Models\Attributes\Factory;
use Lsr\Core\Models\Attributes\Instantiate;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\LoadingType;

/**
 * LaserMaxx Evo5 player model
 *
 * @extends \App\GameModels\Game\Lasermaxx\Player<Game, Team>
 * @phpstan-ignore-next-line
 */
#[PrimaryKey('id_player'), Factory(PlayerFactory::class, ['system' => 'evo5'])]
class Player extends \App\GameModels\Game\Lasermaxx\Player
{
    public const TABLE  = 'evo5_players';
    public const SYSTEM = 'evo5';
    #[Instantiate]
    public BonusCounts $bonus;
    #[ManyToOne(class: Game::class, loadingType: LoadingType::LAZY)]
    public BaseGame $game;
    #[ManyToOne(foreignKey: 'id_team', class: Team::class)]
    public ?\App\GameModels\Game\Team $team = null;

    public function getMines(): int {
        return $this->bonus->getSum();
    }

    public function getBonusCount(): int {
        return $this->bonus->getSum();
    }
}

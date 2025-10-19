<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game\Lasermaxx\Evo5;

use App\GameModels\Factory\PlayerFactory;
use Lsr\Lg\Results\Interface\Models\GameInterface;
use Lsr\Lg\Results\Interface\Models\TeamInterface;
use Lsr\Lg\Results\LaserMaxx\Evo5\BonusCounts;
use Lsr\Lg\Results\LaserMaxx\Evo5\Evo5PlayerInterface;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\Instantiate;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;

/**
 * LaserMaxx Evo5 player model
 *
 * @extends \App\GameModels\Game\Lasermaxx\Player<Game, Team>
 * @implements Evo5PlayerInterface<Game, Team>
 */
#[PrimaryKey('id_player'), Factory(PlayerFactory::class, ['system' => 'evo5'])]
class Player extends \App\GameModels\Game\Lasermaxx\Player implements Evo5PlayerInterface
{
    public const string TABLE = 'evo5_players';
    public const string SYSTEM = 'evo5';
    #[Instantiate]
    public BonusCounts $bonus;
    #[ManyToOne(class: Game::class)]
    public GameInterface $game;
    #[ManyToOne(foreignKey: 'id_team', class: Team::class)]
    public ?TeamInterface $team = null;

    public function getMines() : int {
        return $this->bonus->getSum();
    }

    public function getBonusCount() : int {
        return $this->bonus->getSum();
    }
}

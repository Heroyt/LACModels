<?php

namespace App\GameModels\Game\Lasermaxx\Evo6;

use App\GameModels\Factory\TeamFactory;
use Lsr\Lg\Results\Interface\Models\GameInterface;
use Lsr\Lg\Results\LaserMaxx\Evo6\Evo6TeamInterface;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\NoDB;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;

/**
 * LaserMaxx Evo6 team model
 *
 * @extends \App\GameModels\Game\Lasermaxx\Team<Player, Game>
 * @implements Evo6TeamInterface<Player, Game>
 */
#[
  PrimaryKey('id_team'),
  Factory(TeamFactory::class, ['system' => 'evo6']) // @phpstan-ignore argument.type
]
class Team extends \App\GameModels\Game\Lasermaxx\Team implements Evo6TeamInterface
{
    public const string TABLE = 'evo6_teams';
    public const string SYSTEM = 'evo6';

    /** @var class-string<Player> */
    #[NoDB]
    public string $playerClass = Player::class;

    /** @var Game */
    #[ManyToOne(class: Game::class)]
    public GameInterface $game;
}

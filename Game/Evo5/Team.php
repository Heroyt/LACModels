<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game\Evo5;

use App\GameModels\Factory\TeamFactory;
use Lsr\Lg\Results\Interface\Models\GameInterface;
use Lsr\Lg\Results\LaserMaxx\Evo5\Evo5TeamInterface;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\NoDB;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use OpenApi\Attributes as OA;

/**
 * LaserMaxx Evo5 team model
 *
 * @extends \App\GameModels\Game\Lasermaxx\Team<Player, Game>
 * @phpstan-ignore-next-line
 */
#[PrimaryKey('id_team'), Factory(TeamFactory::class, ['system' => 'evo5']), OA\Schema(schema: 'TeamEvo5')]
class Team extends \App\GameModels\Game\Lasermaxx\Team implements Evo5TeamInterface
{
    public const string TABLE = 'evo5_teams';
    public const string SYSTEM = 'evo5';

    /** @var class-string<Player> */
    #[NoDB, OA\Property]
    public string $playerClass = Player::class;

    /** @var Game */
    #[ManyToOne(class: Game::class), OA\Property(ref: '#/components/schemas/GameEvo5')]
    public GameInterface $game;
}

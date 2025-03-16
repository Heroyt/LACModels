<?php

namespace App\GameModels\Game\Evo6;

use App\GameModels\Factory\TeamFactory;
use Lsr\Lg\Results\Interface\Models\GameInterface;
use Lsr\Lg\Results\LaserMaxx\Evo6\Evo6TeamInterface;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\NoDB;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use OpenApi\Attributes as OA;

/**
 * LaserMaxx Evo6 team model
 *
 * @extends \App\GameModels\Game\Lasermaxx\Team<Player, Game>
 * @phpstan-ignore-next-line
 */
#[PrimaryKey('id_team'), Factory(TeamFactory::class, ['system' => 'evo6']), OA\Schema(schema: 'TeamEvo6')]
class Team extends \App\GameModels\Game\Lasermaxx\Team implements Evo6TeamInterface
{
    public const string TABLE = 'evo6_teams';
    public const string SYSTEM = 'evo6';

    /** @var class-string<Player> */
    #[NoDB, OA\Property]
    public string $playerClass = Player::class;

    /** @var Game */
    #[ManyToOne(class: Game::class), OA\Property(ref: '#/components/schemas/GameEvo6')]
    public GameInterface $game;
}

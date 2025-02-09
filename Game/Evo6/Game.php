<?php

namespace App\GameModels\Game\Evo6;

use App\GameModels\Factory\GameFactory;
use Lsr\Lg\Results\LaserMaxx\Evo6\Evo6GameInterface;
use Lsr\Lg\Results\LaserMaxx\Evo6\GameStyleType;
use Lsr\Lg\Results\LaserMaxx\Evo6\HitGainSettings;
use Lsr\Lg\Results\LaserMaxx\Evo6\RespawnSettings;
use Lsr\Lg\Results\LaserMaxx\Evo6\Scoring;
use Lsr\Lg\Results\LaserMaxx\Evo6\TriggerSpeed;
use Lsr\Lg\Results\LaserMaxx\Evo6\VipSettings;
use Lsr\Lg\Results\LaserMaxx\Evo6\ZombieSettings;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\Instantiate;
use Lsr\Orm\Attributes\NoDB;
use Lsr\Orm\Attributes\PrimaryKey;

/**
 * LaserMaxx Evo6 game model
 *
 * @extends \App\GameModels\Game\Lasermaxx\Game<Team, Player>
 * @phpstan-ignore-next-line
 */
#[PrimaryKey('id_game'), Factory(GameFactory::class, ['system' => 'evo6'])]
class Game extends \App\GameModels\Game\Lasermaxx\Game implements Evo6GameInterface
{
    public const string SYSTEM = 'evo6';
    public const string TABLE = 'evo6_games';

    #[NoDB]
    public string $playerClass = Player::class;
    #[NoDB]
    public string $teamClass = Team::class;
    public bool $blastShots = false;
    #[Instantiate]
    public Scoring $scoring;
    public TriggerSpeed $triggerSpeed = TriggerSpeed::FAST;
    public GameStyleType $gameStyleType = GameStyleType::TEAM;
    #[Instantiate]
    public VipSettings $vipSettings;
    #[Instantiate]
    public ZombieSettings $zombieSettings;
    #[Instantiate]
    public HitGainSettings $hitGainSettings;
    #[Instantiate]
    public RespawnSettings $respawnSettings;

}

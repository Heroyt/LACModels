<?php

namespace App\GameModels\Game\Evo6;

use App\GameModels\Factory\PlayerFactory;
use Lsr\Lg\Results\Interface\Models\GameInterface;
use Lsr\Lg\Results\Interface\Models\TeamInterface;
use Lsr\Lg\Results\LaserMaxx\Evo6\Evo6PlayerInterface;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\NoDB;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use OpenApi\Attributes as OA;

/**
 * LaserMaxx Evo6 player model
 *
 * @extends \App\GameModels\Game\Lasermaxx\Player<Game, Team>
 * @phpstan-ignore-next-line
 */
#[PrimaryKey('id_player'), Factory(PlayerFactory::class, ['system' => 'evo6']), OA\Schema(schema: 'PlayerEvo6')]
class Player extends \App\GameModels\Game\Lasermaxx\Player implements Evo6PlayerInterface
{
    public const string TABLE = 'evo6_players';
    public const string SYSTEM = 'evo6';

	protected const array IMPORT_PROPERTIES = [
		'name',
		'score',
		'skill',
		'vest',
		'shots',
		'accuracy',
		'hits',
		'deaths',
		'position',
		'hitsOther',
		'hitsOwn',
		'deathsOther',
		'deathsOwn',
		'shotPoints',
		'scoreBonus',
		'scorePowers',
		'scoreMines',
		'ammoRest',
		'livesRest',
		'minesHits',
		'vip',
		'myLasermaxx',
		'bonuses',
		'calories',
		'activity',
		'penaltyCount',
		'scorePenalty',
		'scoreEncouragement',
		'scoreActivity',
		'scoreVip',
		'scoreReality',
		'scoreKnockout',
		'birthday',
	];

	#[OA\Property]
    public int $bonuses = 0;
	#[OA\Property]
    public int $activity = 0;
	#[OA\Property]
    public int $calories = 0;
	#[OA\Property]
    public int $scoreActivity = 0;
	#[OA\Property]
    public int $scoreEncouragement = 0;
	#[OA\Property]
    public int $scoreKnockout = 0;
	#[OA\Property]
    public int $scorePenalty = 0;
	#[OA\Property]
    public int $scoreReality = 0;
	#[OA\Property]
    public int $penaltyCount = 0;
	#[OA\Property]
    public bool $birthday = false;
    #[NoDB, OA\Property]
    public int $respawns {
        get {
			assert($this->game instanceof Game);
            if ($this->deaths < $this->game->lives || $this->game->respawnSettings->respawnLives === 0) {
                return 0;
            }
            return (int) floor(($this->deaths - $this->game->lives) / $this->game->respawnSettings->respawnLives);
        }
    }

    #[ManyToOne(class: Game::class), OA\Property(ref: '#/components/schemas/GameEvo6')]
    public GameInterface $game;
    #[ManyToOne(foreignKey: 'id_team', class: Team::class), OA\Property(ref: '#/components/schemas/TeamEvo6')]
    public ?TeamInterface $team = null;

    /**
     * @inheritDoc
     */
    public function getMines() : int {
        return $this->bonuses;
    }

    public function getBonusCount() : int {
        return $this->bonuses;
    }

}

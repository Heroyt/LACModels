<?php

namespace App\GameModels\Game\Lasermaxx;

use App\Exceptions\GameModeNotFoundException;
use App\GameModels\Game\Evo5\Player;
use App\GameModels\Game\Player as BasePlayer;
use Lsr\Lg\Results\LaserMaxx\LaserMaxxGameInterface;
use Lsr\Lg\Results\LaserMaxx\VipSettings;
use Lsr\Lg\Results\LaserMaxx\ZombieSettings;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\Orm\Attributes\Instantiate;
use OpenApi\Attributes as OA;

/**
 * LaserMaxx game model
 *
 * @template T of Team
 * @template P of Player
 *
 * @extends \App\GameModels\Game\Game<T, P>
 */
#[OA\Schema(schema: 'GameLmx')]
abstract class Game extends \App\GameModels\Game\Game implements LaserMaxxGameInterface
{
    public int $fileNumber;
    /** @var int Initial lives */
    public int $lives = 9999;
    /** @var int Initial ammo count */
    public int $ammo = 9999;
    /** @var int Respawn time in seconds */
    public int $respawn = 5;
    #[OA\Property]
    public int $reloadClips = 0;
    #[OA\Property]
    public bool $allowFriendlyFire = true;
    #[OA\Property]
    public bool $antiStalking = false;
    #[OA\Property]
    public bool $blastShots = false;
    #[OA\Property]
    public bool $switchOn = false;
    #[OA\Property]
    public int $switchLives = 0;
    #[Instantiate]
    public ZombieSettings $zombieSettings;
    #[Instantiate]
    public VipSettings $vipSettings;

    protected bool $minesOn;

    /**
     * @return string[]
     */
    public static function getTeamColors() : array {
        return [
          0 => '#E00000',
          1 => '#008500',
          2 => '#00f',
          3 => '#D100C7',
          4 => '#E0A800',
          5 => '#24AAC2',
        ];
    }

    /**
     * @return string[]
     */
    public static function getTeamNames() : array {
        return [
          0 => lang('Red team', context: 'team.names'),
          1 => lang('Green team', context: 'team.names'),
          2 => lang('Blue team', context: 'team.names'),
          3 => lang('Pink team', context: 'team.names'),
          4 => lang('Yellow team', context: 'team.names'),
          5 => lang('Ocean team', context: 'team.names'),
        ];
    }

    public function insert() : bool {
        $this->getLogger()->info('Inserting game: '.$this->fileNumber);
        return parent::insert();
    }

    public function save() : bool {
        return parent::save() && $this->saveTeams() && $this->savePlayers();
    }

    /**
     * @return array<string,string>
     */
    public function getBestsFields() : array {
        $info = parent::getBestsFields();
        try {
            $mode = $this->mode;
            if (!isset($mode)) {
                return $info;
            }
            if ($mode->isTeam()) {
                if ($mode->settings->bestHitsOwn) {
                    $info['hitsOwn'] = lang('Zabiják vlastního týmu', context: 'bests', domain: 'results');
                }
                if ($mode->settings->bestDeathsOwn) {
                    $info['deathsOwn'] = lang('Největší vlastňák', context: 'bests', domain: 'results');
                }
            }
            if ($mode->settings->bestMines && $mode->settings->mines && $this->isMinesOn()) {
                $info['mines'] = lang('Drtič min', context: 'bests', domain: 'results');
            }
        } catch (GameModeNotFoundException) {
        }
        return $info;
    }

    /**
     * Check if mines were enabled
     *
     * Checks players until it finds one with some mine-related scores.
     *
     * @return bool
     */
    public function isMinesOn() : bool {
        if (!isset($this->minesOn)) {
            $this->minesOn = false;
            /** @var Player $player */
            foreach ($this->players as $player) {
                if ($player->minesHits !== 0 || $player->scoreMines !== 0 || $player->getBonusCount() > 0) {
                    $this->minesOn = true;
                    break;
                }
            }
        }
        return $this->minesOn;
    }

    /**
     * @param  string  $property
     *
     * @return BasePlayer<static,T>|null
     * @throws ValidationException
     */
    public function getBestPlayer(string $property) : ?BasePlayer {
        if ($property === 'mines' && !$this->isMinesOn()) {
            return null;
        }
        return parent::getBestPlayer($property);
    }
}

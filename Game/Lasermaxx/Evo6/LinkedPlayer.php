<?php
declare(strict_types=1);

namespace App\GameModels\Game\Lasermaxx\Evo6;

use App\GameModels\Game\BaseLinkedPlayerProperties;
use App\GameModels\Game\LinkedPlayerInterface;
use Lsr\LaserLiga\PlayerInterface;
use Lsr\Lg\Results\Interface\Models\GameInterface;
use Lsr\Lg\Results\Interface\Models\TeamInterface;

/**
 * @implements LinkedPlayerInterface<Player>
 */
class LinkedPlayer extends Player implements LinkedPlayerInterface
{

    /** @use BaseLinkedPlayerProperties<Player, Team, Game> */
    use BaseLinkedPlayerProperties;

    // Base properties

    public ?int $id {
        get => $this->firstWithMemo('id');
        set(?int $value) {
            $this->id = $value;
        }
    }

    public string $name {
        get => $this->firstWithMemo('name');
        set(string $value) {
            $this->name = $value;
        }
    }

    public int $score {
        get => $this->sumWithMemo('score');
        set(int $value) {
            $this->score = $value;
        }
    }

    public int|string $vest {
        get => $this->firstWithMemo('vest');
        set(int|string $value) {
            $this->vest = $value;
        }
    }

    public int $shots {
        get => $this->sumWithMemo('shots');
        set(int $value) {
            $this->shots = $value;
        }
    }

    public int $accuracy {
        get {
            if (!isset($this->accuracy)) {
                // Re-calculate accuracy from shots and hits
                $this->accuracy = (int)round(100 * $this->hits / $this->shots);
            }
            return $this->accuracy;
        }
        set(int $value) {
            $this->accuracy = $value;
        }
    }

    public int $hits {
        get => $this->sumWithMemo('hits');
        set(int $value) {
            $this->hits = $value;
        }
    }

    public int $deaths {
        get => $this->sumWithMemo('deaths');
        set(int $value) {
            $this->deaths = $value;
        }
    }

    /** @var Team|null */
    public ?TeamInterface $team {
        get => $this->firstWithMemo('team');
        /**
         * @param Team|null $value
         */
        set(?TeamInterface $value) { // @phpstan-ignore propertySetHook.noAssign
            foreach ($this->players as $player) {
                $player->team = $value;
            }
            $this->team = $value;
        }
    }

    /** @var Game */
    public GameInterface $game {
        get => $this->firstWithMemo('game');
        /**
         * @param Game $value
         */
        set(GameInterface $value) { // @phpstan-ignore propertySetHook.noAssign
            foreach ($this->players as $player) {
                $player->game = $value;
            }
            $this->game = $value;
        }
    }

    public ?PlayerInterface $user {
        get => $this->firstWithMemo('user');
        /**
         * @param \App\Models\Auth\Player|null $value
         */
        set(?PlayerInterface $value) {
            foreach ($this->players as $player) {
                $player->user = $value;
            }
            $this->user = $value;
        }
    }

    // Lasermaxx player properties
    public int $shotPoints {
        get => $this->sumWithMemo('shotPoints');
        set(int $value) {
            $this->shotPoints = $value;
        }
    }

    public int $bonusPoints {
        get => $this->sumWithMemo('bonusPoints');
        set(int $value) {
            $this->bonusPoints = $value;
        }
    }

    public int $scorePowers {
        get => $this->sumWithMemo('scorePowers');
        set(int $value) {
            $this->scorePowers = $value;
        }
    }

    public int $scoreMines {
        get => $this->sumWithMemo('scoreMines');
        set(int $value) {
            $this->scoreMines = $value;
        }
    }

    public int $scoreAccuracy {
        get => $this->sumWithMemo('scoreAccuracy');
        set(int $value) {
            $this->scoreAccuracy = $value;
        }
    }

    /** @var int<0, max> */
    public int $ammoRest {
        /** @phpstan-ignore return.type */
        get => $this->sumWithMemo('ammoRest');
        /**
         * @param int<0, max> $value
         */
        set(int $value) {
            $this->ammoRest = $value;
        }
    }

    /** @var int<0, max> */
    public int $minesHits {
        /** @phpstan-ignore return.type */
        get => $this->sumWithMemo('minesHits');
        /**
         * @param int<0, max> $value
         */
        set(int $value) {
            $this->minesHits = $value;
        }
    }

    /** @var int<0, max> */
    public int $hitsOther {
        /** @phpstan-ignore return.type */
        get => $this->sumWithMemo('hitsOther');
        /**
         * @param int<0, max> $value
         */
        set(int $value) {
            $this->hitsOther = $value;
        }
    }

    /** @var int<0, max> */
    public int $hitsOwn {
        /** @phpstan-ignore return.type */
        get => $this->sumWithMemo('hitsOwn');
        /**
         * @param int<0, max> $value
         */
        set(int $value) {
            $this->hitsOwn = $value;
        }
    }

    /** @var int<0, max> */
    public int $deathsOwn {
        /** @phpstan-ignore return.type */
        get => $this->sumWithMemo('deathsOwn');
        /**
         * @param int<0, max> $value
         */
        set(int $value) {
            $this->deathsOwn = $value;
        }
    }

    /** @var int<0, max> */
    public int $deathsOther {
        /** @phpstan-ignore return.type */
        get => $this->sumWithMemo('deathsOther');
        /**
         * @param int<0, max> $value
         */
        set(int $value) {
            $this->deathsOther = $value;
        }
    }

    public bool $vip {
        get => $this->firstWithMemo('vip');
        set(bool $value) {
            $this->vip = $value;
            foreach ($this->players as $player) {
                $player->vip = $value;
            }
        }
    }

    public int $scoreVip {
        get => $this->sumWithMemo('scoreVip');
        set(int $value) {
            $this->scoreVip = $value;
        }
    }

    public string $myLasermaxx {
        get => $this->firstWithMemo('myLasermaxx');
        set(string $value) {
            $this->myLasermaxx = $value;
            foreach ($this->players as $player) {
                $player->myLasermaxx = $value;
            }
        }
    }

    // Evo6 player properties

    public int $bonuses {
        get => $this->sumWithMemo('bonuses');
        set(int $value) {
            $this->bonuses = $value;
        }
    }

    public int $activity {
        get => $this->sumWithMemo('activity');
        set(int $value) {
            $this->activity = $value;
        }
    }

    public int $scoreActivity {
        get => $this->sumWithMemo('scoreActivity');
        set(int $value) {
            $this->scoreActivity = $value;
        }
    }

    public int $scoreEncouragement {
        get => $this->sumWithMemo('scoreEncouragement');
        set(int $value) {
            $this->scoreEncouragement = $value;
        }
    }

    public int $scoreKnockout {
        get => $this->sumWithMemo('scoreKnockout');
        set(int $value) {
            $this->scoreKnockout = $value;
        }
    }

    public int $scorePenalty {
        get => $this->sumWithMemo('scorePenalty');
        set(int $value) {
            $this->scorePenalty = $value;
        }
    }

    public int $scoreReality {
        get => $this->sumWithMemo('scoreReality');
        set(int $value) {
            $this->scoreReality = $value;
        }
    }

    public int $penaltyCount {
        get => $this->sumWithMemo('penaltyCount');
        set(int $value) {
            $this->penaltyCount = $value;
        }
    }

    public bool $birthday {
        get {
            if (!isset($this->birthday)) {
                $this->birthday = array_any($this->players, fn($player) => $player->birthday);
            }
            return $this->birthday;
        }
        set(bool $value) {
            $this->birthday = $value;
        }
    }

    public int $respawns {
        get {
            $respawns = 0;
            foreach ($this->players as $player) {
                if ($player->deaths < $player->game->lives) {
                    continue;
                }
                $respawns += (int)floor(($player->deaths - $player->game->lives) / $player->game->respawnSettings->respawnLives);
            }
            return $respawns;
        }
        set(int $value) {
            $this->respawns = $value;
        }
    }

    public function getMines(): int
    {
        return $this->bonuses;
    }

    public function getBonusCount(): int
    {
        return $this->bonuses;
    }

}
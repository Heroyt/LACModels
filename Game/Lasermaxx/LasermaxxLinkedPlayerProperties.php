<?php

declare(strict_types=1);

namespace App\GameModels\Game\Lasermaxx;

trait LasermaxxLinkedPlayerProperties
{
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
}

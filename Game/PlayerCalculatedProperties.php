<?php
declare(strict_types=1);

namespace App\GameModels\Game;

use App\Exceptions\InsufficientRegressionDataException;
use Lsr\Lg\Results\Interface\Models\PlayerInterface;
use Lsr\Orm\Attributes\JsonExclude;
use Lsr\Orm\Attributes\NoDB;

/**
 * @template G of Game
 * @template T of Team
 */
trait PlayerCalculatedProperties
{
    #[NoDB]
    public int $teamNum {
        get => $this->teamNum ?? $this->color;
        set(int $value) => $this->teamNum = $value;
    }

    #[NoDB]
    public int $color {
        get => $this->team->color ?? ($this->game->mode->isSolo() ? 2 : 0);
    }

    #[NoDB]
    public int $miss {
        /** @phpstan-ignore assign.propertyReadOnly */
        get => $this->shots - $this->hits;
    }

    #[NoDB, JsonExclude]
    public PlayerTrophy $trophy {
        get {
            if (!isset($this->trophy)) {
                $this->trophy = new PlayerTrophy($this);
            }
            return $this->trophy;
        }
    }

    /** @var Player<G,T>|null */
    #[NoDB, JsonExclude]
    public ?PlayerInterface $favouriteTarget = null {
        get {
            if (!isset($this->favouriteTarget)) {
                $max = 0;
                foreach ($this->getHitsPlayers() as $hits) {
                    if ($hits->count > $max) {
						/** @phpstan-ignore assign.propertyType */
                        $this->favouriteTarget = $hits->playerTarget;
                        $max = $hits->count;
                    }
                }
            }
            return $this->favouriteTarget;
        }
		set(PlayerInterface|null $value) => $this->favouriteTarget = $value;
    }
    /** @var Player<G,T>|null */
    #[NoDB, JsonExclude]
    public ?PlayerInterface $favouriteTargetOf = null {
        get {
            if (!isset($this->favouriteTargetOf)) {
                $max = 0;
                /** @var static $player */
                foreach ($this->game->players as $player) {
                    if ($player->id === $this->id) {
                        continue;
                    }
                    $hits = $player->getHitsPlayer($this);
                    if ($hits > $max) {
                        $max = $hits;
                        $this->favouriteTargetOf = $player;
                    }
                }
            }
            return $this->favouriteTargetOf;
        }
		set(PlayerInterface|null $value) => $this->favouriteTargetOf = $value;
    }
    public ?float $relativeHits = null {
        get {
            if (!isset($this->relativeHits)) {
                try {
                    $expected = $this->getExpectedAverageHitCount();
                    $diff = $this->hits - $expected;
                    $this->relativeHits = 1 + ($diff / $expected);
                } catch (InsufficientRegressionDataException) {
                    $this->relativeHits = null;
                }
            }
            return $this->relativeHits;
        }
		set(float|null $value) => $this->relativeHits = $value;
    }
    public ?float $relativeDeaths = null {
        get {
            if (!isset($this->relativeDeaths)) {
                try {
                    $expected = $this->getExpectedAverageDeathCount();
                    $diff = $this->deaths - $expected;
                    $this->relativeDeaths = 1 + ($diff / $expected);
                } catch (InsufficientRegressionDataException) {
                    $this->relativeDeaths = null;
                }
            }
            return $this->relativeDeaths;
        }
		set(float|null $value) => $this->relativeDeaths = $value;
    }
}
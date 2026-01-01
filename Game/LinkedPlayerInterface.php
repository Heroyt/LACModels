<?php
declare(strict_types=1);

namespace App\GameModels\Game;

/**
 * @template P of Player
 */
interface LinkedPlayerInterface
{

    /** @var non-empty-array<P> */
    public array $players {
        get;
        set;
    }

    /** @var non-empty-array<int|string> */
    public array $vests {
        get;
        set;
    }

}
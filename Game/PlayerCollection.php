<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game;

use App\Core\Collections\AbstractCollection;
use App\Core\Interfaces\CollectionQueryInterface;
use App\GameModels\Game\Query\PlayerQuery;

/**
 * A collection for player models
 *
 * @template P of Player
 *
 * @property P[] $data
 *
 * @extends AbstractCollection<P>
 */
class PlayerCollection extends AbstractCollection
{
    protected string $type = Player::class;

    /**
     * @return PlayerQuery<P>
     */
    public function query() : CollectionQueryInterface {
        return new PlayerQuery($this);
    }
}

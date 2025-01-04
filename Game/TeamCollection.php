<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game;

use App\Core\Collections\AbstractCollection;
use App\Core\Interfaces\CollectionQueryInterface;
use App\GameModels\Game\Query\TeamQuery;

/**
 * A collection for team models
 *
 * @template T of Team
 *
 * @property T[] $data
 *
 * @extends AbstractCollection<T>
 */
class TeamCollection extends AbstractCollection
{
    public string $type = Team::class;

    /**
     * @return TeamQuery<T>
     */
    public function query() : CollectionQueryInterface {
        /** @var TeamQuery<T> $query */
        $query = new TeamQuery($this);
        return $query;
    }
}

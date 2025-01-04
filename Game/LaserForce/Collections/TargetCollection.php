<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game\LaserForce\Collections;

use App\Core\Collections\AbstractCollection;
use App\Core\Interfaces\CollectionQueryInterface;
use App\GameModels\Game\LaserForce\Collections\Query\TargetQuery;
use App\GameModels\Game\LaserForce\Target;

/**
 * @property Target[] $data
 *
 * @extends AbstractCollection<Target>
 */
class TargetCollection extends AbstractCollection
{
    protected string $type = Target::class;

    /**
     * @return CollectionQueryInterface<Target>
     */
    public function query() : CollectionQueryInterface {
        return new TargetQuery($this);
    }
}

<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game\LaserForce\Collections;

use App\GameModels\Game\LaserForce\Collections\Query\TargetQuery;
use App\GameModels\Game\LaserForce\Target;
use Lsr\Lg\Results\Collections\AbstractCollection;
use Lsr\Lg\Results\Interface\Collections\CollectionQueryInterface;

/**
 * @extends AbstractCollection<Target>
 */
class TargetCollection extends AbstractCollection
{
    protected string $type = Target::class;

    /**
     * @return CollectionQueryInterface
     */
    public function query() : CollectionQueryInterface {
        return new TargetQuery($this);
    }
}

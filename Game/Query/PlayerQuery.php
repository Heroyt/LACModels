<?php

namespace App\GameModels\Game\Query;

use App\Core\Collections\AbstractCollectionQuery;
use App\GameModels\Game\Player;

/**
 * Query object for player models
 *
 * @template P of Player
 *
 * @extends AbstractCollectionQuery<P>
 */
class PlayerQuery extends AbstractCollectionQuery
{
}

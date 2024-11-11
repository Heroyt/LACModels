<?php

namespace App\GameModels\Game\Query;


use App\Core\Collections\AbstractCollectionQuery;
use App\GameModels\Game\Team;

/**
 * Query object for team models
 *
 * @template T of Team
 *
 * @extends AbstractCollectionQuery<T>
 */
class TeamQuery extends AbstractCollectionQuery
{

}
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
 * @extends AbstractCollection<Team>
 */
class TeamCollection extends AbstractCollection
{

	public string $type = Team::class;

	/**
	 * @return CollectionQueryInterface<Team>
	 */
	public function query() : CollectionQueryInterface {
		return new TeamQuery($this);
	}
}
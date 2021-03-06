<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game;

use App\Core\Collections\AbstractCollection;
use App\Core\Interfaces\CollectionQueryInterface;
use App\GameModels\Game\Query\PlayerQuery;

/**
 * @property Player[] $data
 *
 * @extends AbstractCollection<Player>
 */
class PlayerCollection extends AbstractCollection
{

	protected string $type = Player::class;

	/**
	 * @return CollectionQueryInterface<Player>
	 */
	public function query() : CollectionQueryInterface {
		return new PlayerQuery($this);
	}
}
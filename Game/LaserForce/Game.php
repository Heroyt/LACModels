<?php

namespace App\GameModels\Game\LaserForce;

use App\GameModels\Game\LaserForce\Traits\WithTargets;
use Lsr\Core\Models\Attributes\NoDB;

/**
 * @extends \App\GameModels\Game\Game<Team, Player>
 */
class Game extends \App\GameModels\Game\Game
{
	use WithTargets;

	public const TABLE  = 'laserforce_games';
	public const SYSTEM = 'laserForce';

	/** @var Event[] */
	#[NoDB]
	public array $events = [];

	public int $rounds = 0;

	public string $modeName        = '';
	public int    $normalTeamCount = 0;

	/**
	 * @param Event $event
	 *
	 * @return $this
	 */
	public function addEvent(Event $event) : static {
		if (!isset($this->events[$event->time])) {
			$this->events[$event->time] = $event;
		}
		else {
			for ($i = 0; $i < 10; $i++) {
				$key = $event->time.'-'.$i;
				if (!isset($this->events[$key])) {
					$this->events[$key] = $event;
					return $this;
				}
			}
		}
		return $this;
	}
}
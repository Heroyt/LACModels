<?php

namespace App\GameModels\Game\LaserForce\GameModes;

use App\Exceptions\ResultsParseException;
use App\GameModels\Game\LaserForce\Enums\EventType;
use App\GameModels\Game\LaserForce\Event;
use App\GameModels\Game\LaserForce\Interfaces\CustomEventsInterface;
use App\GameModels\Game\LaserForce\Player;

/**
 *
 */
class LaserBall extends TeamDeathmach implements CustomEventsInterface
{

	public string  $name        = 'Laser ball';
	public ?string $description = 'Laser game football!';

	/**
	 * Process anything related to the event
	 *
	 * Allows for adding of extra logic over some events on top of Event::process(). This method should update all necessary values on all the actors and game if necessary.
	 *
	 * @warning Should not add any score. The score is added using a different record.
	 *
	 * @param Event $event
	 *
	 * @return void
	 * @throws ResultsParseException
	 */
	public function processEvent(Event $event) : void {
		switch ($event->type) {
			case EventType::MODE_ACTION_1:
				if (!isset($event->actor2) || !($event->actor2 instanceof Player)) {
					throw new ResultsParseException('LaserBall pass event must have both actors - event time: '.$event->time);
				}
				$event->actor1->shots++;
				$event->actor1->laserBall->passes++;
				$event->actor2->laserBall->ballGot++;
				break;
			case EventType::MODE_ACTION_2:
				$event->actor1->shots++;
				$event->actor1->laserBall->goals++;
				break;
			case EventType::MODE_ACTION_4:
				if (!isset($event->actor2) || !($event->actor2 instanceof Player)) {
					throw new ResultsParseException('LaserBall steal event must have both actors - event time: '.$event->time);
				}
				$event->actor1->shots++;
				$event->actor1->hits++;
				$event->actor1->hitsOther++;
				$event->actor1->laserBall->steals++;
				$event->actor2->deaths++;
				$event->actor2->deathsOther++;
				$event->actor2->laserBall->lost++;
				$event->actor1->addHits($event->actor2);
				break;
			case EventType::MODE_ACTION_5:
				if (!isset($event->actor2)) {
					throw new ResultsParseException('Hit event must have both actors - event time: '.$event->time);
				}
				if ($event->actor2 instanceof Player) {
					$event->actor1->shots++;
					$event->actor1->hits++;
					$event->actor1->hitsOther++;
					$event->actor2->deaths++;
					$event->actor2->deathsOther++;
					$event->actor1->addHits($event->actor2);
				}
				else {
					$event->actor1->targetHitsCount++;
					$event->actor1->addTargetHits($event->actor2);
				}
				break;
			case EventType::MODE_ACTION_6:
				$event->game->rounds++;
				break;
			case EventType::MODE_ACTION_8:
				$event->actor1->laserBall->ballGot++;
				break;
			case EventType::MODE_ACTION_10:
				if (!isset($event->actor2) || !($event->actor2 instanceof Player)) {
					throw new ResultsParseException('LaserBall clear event must have both actors - event time: '.$event->time);
				}
				$event->actor1->shots++;
				$event->actor1->laserBall->clears++;
				$event->actor2->laserBall->ballGot++;
				break;
			default:
				break; // Do nothing
		}
	}
}
<?php

namespace App\GameModels\Game\LaserForce\Interfaces;

use App\GameModels\Game\LaserForce\Event;

interface CustomEventsInterface
{

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
	 */
	public function processEvent(Event $event) : void;

}
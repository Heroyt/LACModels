<?php

namespace App\GameModels\Traits;

use App\GameModels\Game\Game;
use Lsr\Core\Models\Attributes\ManyToOne;
use RuntimeException;

trait WithGame
{

	#[ManyToOne]
	protected ?Game $game;

	/**
	 * @return Game
	 */
	public function getGame() : Game {
		if (!isset($this->game)) {
			throw new RuntimeException('Model has no game assigned');
		}
		return $this->game;
	}

	/**
	 * @param Game $game
	 *
	 * @return static
	 */
	public function setGame(Game $game) : static {
		$this->game = $game;
		return $this;
	}


}
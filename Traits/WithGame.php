<?php

namespace App\GameModels\Traits;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use Lsr\Core\Models\Attributes\ManyToOne;
use RuntimeException;
use Throwable;

trait WithGame
{

	#[ManyToOne]
	public Game $game;

	/**
	 * @return Game
	 * @throws Throwable
	 */
	public function getGame() : Game {
		if (!isset($this->game)) {
			if (isset($this->row->id_game)) {
				$this->game = GameFactory::getById($this->row->id_game, ['system' => $this::SYSTEM]);
				return $this->game;
			}
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
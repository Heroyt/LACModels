<?php

namespace App\GameModels\Traits;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use Lsr\Core\Models\Attributes\ManyToOne;
use RuntimeException;
use Throwable;

/**
 * @template G of Game
 */
trait WithGame
{

	/** @var G */
	#[ManyToOne]
	public Game $game;

	/**
	 * @return G
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
	 * @param G $game
	 *
	 * @return static
	 */
	public function setGame(Game $game) : static {
		$this->game = $game;
		return $this;
	}


}
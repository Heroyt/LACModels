<?php

namespace App\GameModels\Traits;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\LoadingType;
use RuntimeException;
use Throwable;

/**
 * @template G of Game
 */
trait WithGame
{

	/** @var G */
	#[ManyToOne(loadingType: LoadingType::LAZY)]
	public Game $game;

	/**
	 * @return G
	 * @throws Throwable
	 */
	public function getGame() : Game {
		if (!isset($this->game)) {
			$gameId = $this->row->id_game ?? $this->relationIds['game'];
			if (isset($gameId)) {
				$this->game = GameFactory::getById($gameId, ['system' => $this::SYSTEM]);
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
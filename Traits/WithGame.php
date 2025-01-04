<?php

namespace App\GameModels\Traits;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use RuntimeException;
use Throwable;

/**
 * @template G of Game
 */
trait WithGame
{
    /** @var G */
    #[ManyToOne(factoryMethod: 'loadGame')]
    public Game $game;

    /**
     * @return G
     * @throws Throwable
     */
    public function loadGame() : Game {
        $gameId = $this->row?->id_game ?? $this->relationIds['game'] ?? null;
        $game = null;
        if (isset($gameId)) {
            $game = GameFactory::getById($gameId, ['system' => $this::SYSTEM]);
        }
        if ($game === null) {
            throw new RuntimeException('Model has no game assigned');
        }
        return $game;
    }

    /**
     * @param  G  $game
     *
     * @return static
     */
    public function setGame(Game $game) : static {
        $this->game = $game;
        return $this;
    }
}

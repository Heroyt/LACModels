<?php

namespace App\GameModels\Traits;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use Lsr\Lg\Results\Interface\Models\GameInterface;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use RuntimeException;
use Throwable;

/**
 * @template G of Game
 */
trait WithGame
{
    /** @var G */
    #[ManyToOne(class: Game::class, factoryMethod: 'loadGame')]
    public GameInterface $game;

    /**
     * @return G
     * @throws Throwable
     */
    public function loadGame() : Game {
        /** @phpstan-ignore nullsafe.neverNull */
        $gameId = $this->row?->id_game ?? $this->relationIds['game'] ?? null;
        /** @var G|null $game */
        $game = null;
        if (isset($gameId)) {
            /** @var G|null $game */
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
     * @return $this
     */
    public function setGame(GameInterface $game) : static {
        $this->game = $game;
        return $this;
    }

    public function saveGame() : bool {
        return $this->game->save();
    }
}

<?php

declare(strict_types=1);

namespace App\GameModels\Game;

use Lsr\Lg\Results\PlayerCollection;

/**
 * @template P of Player&LinkedPlayerInterface
 *
 * @property-read class-string<P> $linkedPlayerClass
 */
trait WithLinkedPlayers
{
    public function loadPlayers(): PlayerCollection
    {
        $this->players = parent::loadPlayers();
        $this->linkPlayers();
        return $this->players;
    }

    /**
     * Find linked players and group them into LinkedPlayer instances.
     *
     * Linked player = Player with the same user or the same name and team.
     */
    public function linkPlayers(): void
    {
        // Find linked players
        $uniquePlayers = [];
        foreach ($this->players as $player) {
            $key = $player->userId ?? ($player->name . '_' . $player->color);
            $uniquePlayers[$key] ??= []; // Instantiate array if not exists
            $uniquePlayers[$key][] = $player;
        }

        $linked = false;
        $linkedVests = [];

        foreach ($uniquePlayers as $players) {
            if (count($players) < 2) {
                continue; // No linking needed
            }

            $linked = true;

            // Create a linked player
            /** @var P $linkedPlayer */
            $linkedPlayer = new ($this->linkedPlayerClass)();
            $linkedPlayer->players = $players;

            // Replace original players with the linked player
            foreach ($players as $player) {
                $linkedVests[$player->vest] = $linkedPlayer->vests; // Remember vests of linked players for hit merging
                $this->players->remove($player);
            }
            /** @phpstan-ignore argument.type */
            $this->players->set($linkedPlayer, $linkedPlayer->vest);
        }

        if (!$linked) {
            return;
        }

        // Sort players and set player position for linked players
        $order = 1;
        foreach ($this->playersSorted as $player) {
            $player->position = $order;
            $order++;
        }

        // Sum hits for linked players
        foreach ($this->players as $player) {
            $hits = $player->getHitsPlayers();
            foreach ($hits as $vest => $hit) {
                if (!isset($linkedVests[$vest])) {
                    continue;
                }
                // Add hits from linked vests
                foreach ($linkedVests[$vest] as $linkedVest) {
                    if ($linkedVest === $vest) {
                        continue;
                    }
                    $hit->count += $hits[$linkedVest]->count ?? 0;
                }
            }
        }
    }
}

<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game\Lasermaxx\GameModes;

use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\GameModes\AbstractMode;
use App\GameModels\Game\GameModes\CustomLoadMode;
use App\Tools\GameLoading\LasermaxxLoadData;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\PrimaryKey;

/**
 * Special LaserMaxx game mode
 */
#[PrimaryKey('id_mode')]
#[Factory(GameModeFactory::class)] // @phpstan-ignore-line
class Barvicky extends AbstractMode implements CustomLoadMode
{
    use LaserMaxxScores;


    public string $name = 'Barvičky';

    /**
     * Get a JavaScript file to load which should modify the new game form.
     *
     * The JavaScript file should contain only one class which extends the CustomLoadMode class.
     *
     * @return string Script name or empty string
     */
    public function getNewGameScriptToRun() : string {
        return 'barvicky';
    }

    /**
     * @inheritDoc
     */
    public function modifyGameDataBeforeLoad(LasermaxxLoadData $loadData, array $data) : LasermaxxLoadData {
        // Shuffle teams
        if (isset($data['hiddenTeams']) && $data['hiddenTeams'] === '1') {
            $teamCount = count($loadData->teams);
            $pKeys = array_keys($loadData->players);
            shuffle($pKeys);

            foreach ($loadData->teams as $key => $team) {
                $loadData->teams[$key]->playerCount = 0;
            }

            $i = 0;
            foreach ($pKeys as $pKey) {
                $loadData->players[$pKey]->team = (string) $loadData->teams[$i]->key;
                $loadData->teams[$i]->playerCount++;
                $i = ($i + 1) % $teamCount;
            }
        }

        // Add starting team color meta
        foreach ($loadData->players as $player) {
            $loadData->meta['p'.$player->vest.'-startTeam'] = $player->team;
        }
        return $loadData;
    }
}

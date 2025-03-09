<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game\Lasermaxx\GameModes;

use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\GameModes\CustomLoadMode;
use App\Tools\GameLoading\LasermaxxLoadData;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\PrimaryKey;

/**
 * Special LaserMaxx Evo5 game mode
 */
#[PrimaryKey('id_mode')]
#[Factory(GameModeFactory::class)] // @phpstan-ignore-line
abstract class Tma extends TeamDeathmatch implements CustomLoadMode
{
    use LaserMaxxScores;


    public string $name = 'T.M.A.';

    public function getNewGameScriptToRun() : string {
        return '';
    }

    public function modifyGameDataBeforeLoad(LasermaxxLoadData $loadData, array $data) : LasermaxxLoadData {
        foreach ($loadData->players as $player) {
            $player->team = '0'; // Red team
        }
        $loadData->soloTeam = 0;
        return $loadData;
    }
}

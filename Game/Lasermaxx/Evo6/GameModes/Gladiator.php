<?php
declare(strict_types=1);

namespace App\GameModels\Game\Lasermaxx\Evo6\GameModes;

use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\GameModes\CustomLoadMode;
use App\Tools\GameLoading\LasermaxxLoadData;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\PrimaryKey;

#[
  PrimaryKey('id_mode'),
  Factory(GameModeFactory::class) // @phpstan-ignore argument.type
]
class Gladiator extends Deathmatch implements CustomLoadMode
{
    public string $name = 'Gladiator';

    public function getNewGameScriptToRun() : string {
        return 'gladiator';
    }

    public function modifyGameDataBeforeLoad(LasermaxxLoadData $loadData, array $data) : LasermaxxLoadData {
        foreach ($loadData->players as $player) {
            $player->vip = true;
        }
        return $loadData;
    }
}
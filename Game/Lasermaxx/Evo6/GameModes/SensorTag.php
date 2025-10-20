<?php
declare(strict_types=1);

namespace App\GameModels\Game\Lasermaxx\Evo6\GameModes;

use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\Lasermaxx\Evo6\Game;
use Lsr\Lg\Results\Interface\Models\GameInterface;
use Lsr\Lg\Results\Interface\Models\ModifyScoresMode;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\PrimaryKey;

#[
  PrimaryKey('id_mode'),
  Factory(GameModeFactory::class) // @phpstan-ignore argument.type
]
class SensorTag extends Deathmatch implements ModifyScoresMode
{

    public string $name = 'Sensor Tag';

    /**
     * @template G of Game
     * @param  G  $game
     * @return void
     */
    public function modifyResults(GameInterface $game) : void {
        foreach ($game->players as $player) {
            if ($player->getRemainingLives() > 0) {
                $player->scoreBonus += 1000;
                $player->score += 1000;
            }
        }
    }
}
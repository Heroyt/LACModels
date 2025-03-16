<?php
declare(strict_types=1);

namespace App\GameModels\Game\Evo6\GameModes;

use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\Evo6\Game;
use App\GameModels\Game\Evo6\Player;
use Lsr\Lg\Results\Interface\Models\GameInterface;
use Lsr\Lg\Results\Interface\Models\ModifyScoresMode;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\PrimaryKey;

/** @phpstan-ignore argument.type */
#[PrimaryKey('id_mode'), Factory(GameModeFactory::class)]
class SensorTag extends Deathmatch implements ModifyScoresMode
{

    public string $name = 'Sensor Tag';


    public function modifyResults(GameInterface $game) : void {
        assert($game instanceof Game);

        /** @var Player $player */
        foreach ($game->players as $player) {
            if ($player->getRemainingLives() > 0) {
                $player->scoreBonus += 1000;
                $player->score += 1000;
            }
        }
    }
}
<?php

namespace App\GameModels\Game\GameModes;

use App\GameModels\Factory\GameModeFactory;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\PrimaryKey;

/**
 * Basic team game mode
 */
#[PrimaryKey('id_mode')]
#[Factory(GameModeFactory::class)] // @phpstan-ignore-line
class CustomTeamMode extends AbstractMode
{
    public function getSoloAlternative() : string {
        return CustomSoloMode::class;
    }
}

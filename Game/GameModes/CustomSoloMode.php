<?php

namespace App\GameModels\Game\GameModes;

use App\GameModels\Factory\GameModeFactory;
use Lsr\Lg\Results\Enums\GameModeType;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\PrimaryKey;

/**
 * Basic solo game mode model
 */
#[PrimaryKey('id_mode')]
#[Factory(GameModeFactory::class)] // @phpstan-ignore-line // @phpstan-ignore-line
class CustomSoloMode extends AbstractMode
{
    public GameModeType $type = GameModeType::SOLO;

    public function getTeamAlternative() : string {
        return CustomTeamMode::class;
    }
}

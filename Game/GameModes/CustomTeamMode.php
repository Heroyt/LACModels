<?php

namespace App\GameModels\Game\GameModes;

use App\GameModels\Factory\GameModeFactory;
use Lsr\Core\Models\Attributes\Factory;
use Lsr\Core\Models\Attributes\PrimaryKey;

/**
 * Basic team game mode
 */
#[PrimaryKey('id_mode')]
#[Factory(GameModeFactory::class)] // @phpstan-ignore-line
class CustomTeamMode extends AbstractMode
{

}
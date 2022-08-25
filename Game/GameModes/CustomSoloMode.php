<?php

namespace App\GameModels\Game\GameModes;

use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\Enums\GameModeType;
use Lsr\Core\Models\Attributes\Factory;
use Lsr\Core\Models\Attributes\PrimaryKey;

/**
 * Basic solo game mode model
 */
#[PrimaryKey('id_mode')]
#[Factory(GameModeFactory::class)] // @phpstan-ignore-line // @phpstan-ignore-line
class CustomSoloMode extends AbstractMode
{

	public GameModeType $type = GameModeType::SOLO;

}
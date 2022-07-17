<?php

namespace App\GameModels\Game\GameModes;

use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\Enums\GameModeType;
use Lsr\Core\Models\Attributes\Factory;
use Lsr\Core\Models\Attributes\PrimaryKey;

/**
 * Basic Deathmach game mode
 */
#[PrimaryKey('id_mode')]
#[Factory(GameModeFactory::class)]
class Deathmach extends AbstractMode
{

	public GameModeType $type        = GameModeType::SOLO;
	public string       $name        = 'Deathmach';
	public ?string      $description = 'Free for all game type.';

}
<?php

namespace App\GameModels\Game\GameModes;

use App\GameModels\Factory\GameModeFactory;
use Lsr\Core\Models\Attributes\Factory;
use Lsr\Core\Models\Attributes\PrimaryKey;

#[PrimaryKey('id_mode')]
#[Factory(GameModeFactory::class)]
class TeamDeathmach extends AbstractMode
{

	public string  $name        = 'Team deathmach';
	public ?string $description = 'Classic team game type.';

}
<?php

namespace App\GameModels\Game\Evo5\GameModes;

use App\GameModels\Factory\GameModeFactory;
use Lsr\Core\Models\Attributes\Factory;
use Lsr\Core\Models\Attributes\PrimaryKey;

#[PrimaryKey('id_mode')]
#[Factory(GameModeFactory::class)]
class Deathmach extends \App\GameModels\Game\GameModes\Deathmach
{

}
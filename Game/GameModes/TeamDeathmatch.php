<?php

namespace App\GameModels\Game\GameModes;

use App\GameModels\Factory\GameModeFactory;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\PrimaryKey;

/**
 * Basic team Deathmatch game mode
 */
#[PrimaryKey('id_mode')]
#[Factory(GameModeFactory::class)] // @phpstan-ignore-line
class TeamDeathmatch extends AbstractMode
{
    public string $name = 'Team deathmatch';
    public ?string $description = 'Classic team game type.';

    public function getSoloAlternative() : string {
        return Deathmatch::class;
    }
}

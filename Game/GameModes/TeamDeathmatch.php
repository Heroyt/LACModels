<?php

namespace App\GameModels\Game\GameModes;

use App\GameModels\Factory\GameModeFactory;
use Lsr\Lg\Results\Interface\Models\TeamGameModeInterface;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\PrimaryKey;

/**
 * Basic team Deathmatch game mode
 */
#[PrimaryKey('id_mode')]
#[Factory(GameModeFactory::class)] // @phpstan-ignore-line
class TeamDeathmatch extends AbstractMode implements TeamGameModeInterface
{
    public string $name = 'Team deathmatch';
    public ?string $description = 'Classic team game type.';

    public function getSoloAlternative() : string {
        return Deathmatch::class;
    }
}

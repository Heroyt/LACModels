<?php

namespace App\GameModels\Game\GameModes;

use App\GameModels\Factory\GameModeFactory;
use Lsr\Lg\Results\Enums\GameModeType;
use Lsr\Lg\Results\Interface\Models\SoloGameModeInterface;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\PrimaryKey;

/**
 * Basic Deathmatch game mode
 */
#[PrimaryKey('id_mode')]
#[Factory(GameModeFactory::class)] // @phpstan-ignore-line
class Deathmatch extends AbstractMode implements SoloGameModeInterface
{
    public GameModeType $type = GameModeType::SOLO;
    public string $name = 'Deathmatch';
    public ?string $description = 'Free for all game type.';

    public function getTeamAlternative() : string {
        return TeamDeathmatch::class;
    }
}

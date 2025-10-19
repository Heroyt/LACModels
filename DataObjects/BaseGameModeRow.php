<?php

declare(strict_types=1);

namespace App\GameModels\DataObjects;

use Lsr\Lg\Results\Enums\GameModeType;

readonly class BaseGameModeRow
{
    public function __construct(
      public int          $id_mode = 0,
      public string       $name = '',
      public ?string      $systems = null,
      public GameModeType $type = GameModeType::TEAM,
    ) {}
}

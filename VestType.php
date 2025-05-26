<?php
declare(strict_types=1);

namespace App\GameModels;

use OpenApi\Attributes as OA;

#[OA\Schema(type:'string')]
enum VestType : string
{

    case VEST = 'vest';
    case GUN  = 'gun';

    public function getReadableName() : string {
        return match ($this) {
            self::VEST => lang('Vesta', context: 'vest.type'),
            self::GUN  => lang('Zbraň', context: 'vest.type'),
        };
    }
}

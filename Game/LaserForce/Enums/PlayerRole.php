<?php

namespace App\GameModels\Game\LaserForce\Enums;

enum PlayerRole : string
{
    case PLAYER = 'player';

    case HEAVY = 'heavy';
    case SCOUT = 'scout';
    case CAPTAIN = 'captain';
    case MEDIC = 'medic';
    case AMMO  = 'ammo';

    public static function getForSpaceMarines(int $role) : PlayerRole {
        return match ($role) {
            1 => self::CAPTAIN,
            2 => self::SCOUT,
            3 => self::HEAVY,
            4 => self::MEDIC,
            5 => self::AMMO,
            default => self::PLAYER,
        };
    }
}

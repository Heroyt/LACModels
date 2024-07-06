<?php

namespace App\GameModels\Game\Enums;

/**
 * Paper orientation for result printing
 *
 * @method static tryFrom(string $value)
 */
enum PrintOrientation: string
{
    case portrait = 'portrait';
    case landscape = 'landscape';
}

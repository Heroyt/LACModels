<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */
namespace App\GameModels\Game\Enums;

use OpenApi\Attributes as OA;

/**
 * Types of game modes
 *
 * @method static |null tryFrom(string $value)
 * @method static from(mixed $value)
 * @property string $value
 */
#[OA\Schema(type: 'string')]
enum GameModeType: string
{
	case TEAM = 'TEAM';
	case SOLO = 'SOLO';
}
<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */
namespace App\GameModels\Game\Enums;

use OpenApi\Attributes as OA;

/**
 * Types of game modes
 *
 * @method static GameModeType|null tryFrom(string $value)
 * @method static GameModeType from(mixed $value)
 * @property string $value
 */
#[OA\Schema(type: 'string', example: 'TEAM')]
enum GameModeType: string
{
	case TEAM = 'TEAM';
	case SOLO = 'SOLO';
}
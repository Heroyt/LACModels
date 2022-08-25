<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */
namespace App\GameModels\Game\Enums;

/**
 * Types of game modes
 *
 * @method static tryFrom(string $value)
 * @method static from(mixed $value)
 * @property string $value
 */
enum GameModeType: string
{
	case TEAM = 'TEAM';
	case SOLO = 'SOLO';
}
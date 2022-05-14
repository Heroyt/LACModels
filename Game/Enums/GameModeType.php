<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */
namespace App\GameModels\Game\Enums;

enum GameModeType: string
{
	case TEAM = 'TEAM';
	case SOLO = 'SOLO';
}
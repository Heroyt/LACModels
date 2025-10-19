<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game\Lasermaxx\Evo5;

/**
 * PlayerHit class specific for LaserMaxx Evo5 system
 *
 * @extends \App\GameModels\Game\PlayerHit<Player>
 */
class PlayerHit extends \App\GameModels\Game\PlayerHit
{
    public const string TABLE = 'evo5_hits';
}

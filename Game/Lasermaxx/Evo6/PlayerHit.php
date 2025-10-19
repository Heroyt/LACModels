<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game\Lasermaxx\Evo6;

/**
 * PlayerHit class specific for LaserMaxx Evo6 system
 *
 * @extends \App\GameModels\Game\PlayerHit<Player>
 */
class PlayerHit extends \App\GameModels\Game\PlayerHit
{
    public const string TABLE = 'evo6_hits';
}

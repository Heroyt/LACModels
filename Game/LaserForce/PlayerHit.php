<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game\LaserForce;

/**
 * PlayerHit class specific for LaserMaxx Evo5 system
 */
class PlayerHit extends \App\GameModels\Game\PlayerHit
{
    public const string TABLE = 'laserforce_hits';
}

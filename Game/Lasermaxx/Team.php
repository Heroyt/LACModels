<?php

namespace App\GameModels\Game\Lasermaxx;

use Lsr\Lg\Results\LaserMaxx\LaserMaxxTeamInterface;

/**
 * LaserMaxx team model
 *
 * @template P of Player
 * @template G of Game
 *
 * @extends \App\GameModels\Game\Team<P, G>
 * @implements LaserMaxxTeamInterface<P, G>
 */
abstract class Team extends \App\GameModels\Game\Team implements LaserMaxxTeamInterface
{
}

<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game\Lasermaxx\GameModes;

use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\GameModes\CustomResultsMode;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\PrimaryKey;

/**
 * Special LaserMaxx Evo5 game mode
 */
#[PrimaryKey('id_mode')]
#[Factory(GameModeFactory::class)] // @phpstan-ignore-line
abstract class M100Naboju extends \App\GameModels\Game\GameModes\Deathmatch implements CustomResultsMode
{
    use LaserMaxxScores;


    public string $name = '100 nábojů';

    /**
     * Get a template file containing custom results
     *
     * @return string Path to template file
     */
    public function getCustomResultsTemplate() : string {
        return 'naboju';
    }

}

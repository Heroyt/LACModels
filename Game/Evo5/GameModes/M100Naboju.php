<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game\Evo5\GameModes;

use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\GameModes\CustomResultsMode;
use App\GameModels\Game\Lasermaxx\GameModes\LaserMaxxScores;
use App\Gate\Screens\Results\LaserMaxx100NabojuResultsScreen;
use Lsr\Core\Models\Attributes\Factory;
use Lsr\Core\Models\Attributes\PrimaryKey;

/**
 * Special LaserMaxx Evo5 game mode
 */
#[PrimaryKey('id_mode')]
#[Factory(GameModeFactory::class)] // @phpstan-ignore-line
class M100Naboju extends \App\GameModels\Game\GameModes\Deathmach implements CustomResultsMode
{
    use LaserMaxxScores;


    public string $name = '100 nábojů';

    /**
     * Get a template file containing custom results
     *
     * @return string Path to template file
     */
    public function getCustomResultsTemplate(): string {
        return 'naboju';
    }

    /**
     * Get a template file containing the custom gate results
     *
     * @return string Path to template file
     */
    public function getCustomGateScreen(): string {
        return LaserMaxx100NabojuResultsScreen::class;
    }
}

<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game\Evo5\GameModes;

use App\GameModels\Factory\GameModeFactory;
use Lsr\Core\Models\Attributes\Factory;
use Lsr\Core\Models\Attributes\PrimaryKey;

/**
 * LaserMaxx Evo5 team deathmach game mode
 */
#[PrimaryKey('id_mode')]
#[Factory(GameModeFactory::class)] // @phpstan-ignore-line
class TeamDeathmach extends \App\GameModels\Game\GameModes\TeamDeathmach
{

	use Evo5Scores;

	public function getSoloAlternative() : string {
		return Deathmach::class;
	}
}
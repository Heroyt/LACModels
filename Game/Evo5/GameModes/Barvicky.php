<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game\Evo5\GameModes;

use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\GameModes\AbstractMode;
use App\GameModels\Game\GameModes\CustomLoadMode;
use App\GameModels\Game\Lasermaxx\GameModes\LaserMaxxScores;
use Lsr\Core\Models\Attributes\Factory;
use Lsr\Core\Models\Attributes\PrimaryKey;

/**
 * Special LaserMaxx Evo5 game mode
 */
#[PrimaryKey('id_mode')]
#[Factory(GameModeFactory::class)] // @phpstan-ignore-line
class Barvicky extends AbstractMode implements CustomLoadMode
{

	use LaserMaxxScores;


	public string $name = 'Barvičky';

	/**
	 * Get a JavaScript file to load which should modify the new game form.
	 *
	 * The JavaScript file should contain only one class which extends the CustomLoadMode class.
	 *
	 * @return string Script name or empty string
	 */
	public function getNewGameScriptToRun() : string {
		return 'barvicky';
	}

	/**
	 * Modify the game data which should be passed to the load file.
	 *
	 * @param array{
	 *      meta:array<string,string>,
	 *      players:array{
	 *        vest:int,
	 *        name:string,
	 *        team:string,
	 *        vip:bool
	 *      }[],
	 *      teams:array{
	 *        key:string,
	 *        name:string,
	 *        playerCount:int
	 *      }[]
	 *    } $data
	 *
	 * @return array{
	 *      meta:array<string,string>,
	 *      players:array{
	 *        vest:int,
	 *        name:string,
	 *        team:string,
	 *        vip:bool
	 *      }[],
	 *      teams:array{
	 *        key:string,
	 *        name:string,
	 *        playerCount:int
	 *      }[]
	 *    } Modified data
	 */
	public function modifyGameDataBeforeLoad(array $data) : array {
		// Shuffle teams
		if (isset($_POST['hiddenTeams']) && $_POST['hiddenTeams'] === '1') {
			$teamCount = count($data['teams']);
			$pKeys = array_keys($data['players']);
			shuffle($pKeys);

			foreach ($data['teams'] as $key => $team) {
				$data['teams'][$key]['playerCount'] = 0;
			}

			$i = 0;
			foreach ($pKeys as $pKey) {
				$data['players'][$pKey]['team'] = $data['teams'][$i]['key'];
				$data['teams'][$i]['playerCount']++;
				$i = ($i + 1) % $teamCount;
			}
		}

		// Add starting team color meta
		foreach ($data['players'] as $player) {
			$data['meta']['p'.$player['vest'].'-startTeam'] = $player['team'];
		}
		return $data;
	}
}
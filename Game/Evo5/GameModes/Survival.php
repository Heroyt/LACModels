<?php

namespace App\GameModels\Game\Evo5\GameModes;

use App\GameModels\Game\GameModes\CustomResultsMode;
use Lsr\Core\Controller;

class Survival extends \App\GameModels\Game\GameModes\Deathmach implements CustomResultsMode
{

	public string $name = 'Survival';

	/**
	 * Get a template file containing custom results
	 *
	 * @return string Path to template file
	 */
	public function getCustomResultsTemplate(Controller $controller) : string {
		return '';
	}

	/**
	 * Get a template file containing the custom gate results
	 *
	 * @return string Path to template file
	 */
	public function getCustomGateTemplate(Controller $controller) : string {
		return 'pages/gate/modes/survival';
	}
}
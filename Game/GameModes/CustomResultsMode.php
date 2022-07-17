<?php

namespace App\GameModels\Game\GameModes;

use Lsr\Core\Controller;

/**
 * Interface for game modes which should use a different results template
 */
interface CustomResultsMode
{

	/**
	 * Get a template file containing custom results
	 *
	 * @return string Path to template file
	 */
	public function getCustomResultsTemplate(Controller $controller) : string;

	/**
	 * Get a template file containing the custom gate results
	 *
	 * @return string Path to template file
	 */
	public function getCustomGateTemplate(Controller $controller) : string;

}
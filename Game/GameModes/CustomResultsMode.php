<?php

namespace App\GameModels\Game\GameModes;

use App\Gate\Screens\GateScreen;
use App\Gate\Screens\Results\ResultsScreenInterface;
use Lsr\Core\Controllers\Controller;

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
	 * Get a custom gate screen that will show the results for this mode
	 *
	 * @return class-string<ResultsScreenInterface&GateScreen> Custom results gate screen to use
	 */
	public function getCustomGateScreen(): string;

}
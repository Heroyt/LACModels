<?php
declare(strict_types=1);

namespace App\GameModels\Game\GameModes;

interface CustomPlayerResultsMode
{


	public function getCustomPlayerTemplate() : string;

}
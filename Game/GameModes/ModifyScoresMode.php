<?php

namespace App\GameModels\Game\GameModes;

use App\GameModels\Game\Game;

interface ModifyScoresMode
{
    public function modifyResults(Game $game): void;
}

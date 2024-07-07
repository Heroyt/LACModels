<?php

namespace App\GameModels\Game\GameModes;

use App\Tools\GameLoading\LasermaxxGameLoader;
use App\Tools\GameLoading\LasermaxxLoadData;

/**
 * Interface for game modes which should somehow modify the game load process
 *
 * @phpstan-import-type GameData from LasermaxxGameLoader
 */
interface CustomLoadMode
{
    /**
     * Get a JavaScript file to load which should modify the new game form.
     *
     * The JavaScript file should contain only one class which extends the CustomLoadMode class.
     *
     * @return string Script name or empty string
     */
    public function getNewGameScriptToRun(): string;

    /**
     * Modify the game data which should be passed to the load file.
     *
     * @param  LasermaxxLoadData  $loadData
     * @param  GameData|array<string,mixed>  $data
     *
     * @return LasermaxxLoadData Modified data
     */
    public function modifyGameDataBeforeLoad(LasermaxxLoadData $loadData, array $data): LasermaxxLoadData;
}

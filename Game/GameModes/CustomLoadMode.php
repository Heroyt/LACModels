<?php

namespace App\GameModels\Game\GameModes;

/**
 * Interface for game modes which should somehow modify the game load process
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
     * @param array{
     *   meta:array<string,string|numeric>,
     *   players:array{
     *       vest:int,
     *       name:string,
     *       vip:bool,
     *       team:int,
     *       code?:string
     *   }[],
     *   teams:array{
     *       key:int,
     *       name:string,
     *       playerCount:int
     *   }
     *   } $data
     *
     * @return array{
     *   meta:array<string,string|numeric>,
     *   players:array{
     *       vest:int,
     *       name:string,
     *       vip:bool,
     *       team:int,
     *       code?:string
     *   }[],
     *   teams:array{
     *       key:int,
     *       name:string,
     *       playerCount:int
     *   }
     *   } Modified data
     */
    public function modifyGameDataBeforeLoad(array $data): array;
}

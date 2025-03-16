<?php

namespace App\GameModels\Game\GameModes;

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
    public function getCustomResultsTemplate() : string;
}

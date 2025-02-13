<?php

namespace App\GameModels\Game\GameModes;

use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\Game;
use App\GameModels\Game\ModeSettings;
use App\GameModels\Game\Player;
use App\GameModels\Game\Team;
use App\Models\BaseModel;
use App\Models\GameModeVariation;
use App\Models\GameModeVariationValue;
use App\Models\System;
use App\Models\SystemType;
use Dibi\Row;
use Lsr\Db\DB;
use Lsr\Lg\Results\Enums\GameModeType;
use Lsr\Lg\Results\Interface\Models\GameInterface;
use Lsr\Lg\Results\Interface\Models\GameModeInterface;
use Lsr\Lg\Results\LaserMaxx\LaserMaxxGameInterface;
use Lsr\Lg\Results\TeamCollection;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\ObjectValidation\Attributes\Required;
use Lsr\ObjectValidation\Attributes\StringLength;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\Instantiate;
use Lsr\Orm\Attributes\NoDB;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Exceptions\ModelNotFoundException;

/**
 * Base class for all game mode models
 */
#[PrimaryKey('id_mode')]
#[Factory(GameModeFactory::class)] // @phpstan-ignore-line
class AbstractMode extends BaseModel implements GameModeInterface
{
    public const string TABLE = 'game_modes';

    #[Required]
    #[StringLength(min: 1, max: 20)]
    public string $name = '';
    public string $alias = '';
    public ?string $description = '';
    public GameModeType $type = GameModeType::TEAM;
    public ?string $loadName = '';
    public ?string $systems = null;
    public string $teams = '';
    #[Instantiate]
    public ModeSettings $settings;
    public bool $rankable = true;
    public bool $active = true;
    public bool $public = true;
    /** @var GameModeVariationValue[][] */
    #[NoDB]
    public array $variations = [] {
        get {
            if (empty($this->variations)) {
                /** @var array<int,array<string,Row>> $rows */
                $rows = DB::select(GameModeVariation::TABLE_VALUES, '[id_variation], [value], [suffix], [order]')
                          ->where('[id_mode] = %i', $this->id)
                          ->orderBy('[id_variation], [order]')
                          ->cacheTags('mode.variations', 'mode.'.$this->id, 'mode.'.$this->id.'.variations')
                          ->fetchAssoc('id_variation|value');
                foreach ($rows as $variationId => $values) {
                    if (!isset($this->variations[$variationId])) {
                        $this->variations[$variationId] = [];
                    }
                    foreach ($values as $value) {
                        $this->variations[$variationId][] = new GameModeVariationValue(
                          GameModeVariation::get($variationId),
                          $this,
                          $value->value,
                          $value->suffix,
                          $value->order
                        );
                    }
                }
            }
            return $this->variations;
        }
    }

    /** @var System[] */
    #[NoDB]
    public array $allowedSystems {
        get {
            if ($this->systems === null) {
                return System::getAll();
            }
            $systems = [];
            foreach (explode(',', $this->systems) as $system) {
                if (is_numeric($system)) {
                    $systems[] = System::get((int) $system);
                    continue;
                }
                $type = SystemType::tryFrom($system);
                if ($type === null) {
                    continue;
                }
                foreach (System::getForType($type) as $s) {
                    $systems[] = $s;
                }
            }
            return $systems;
        }
        /**
         * @param  System[]  $systems
         */
        set(array $systems) {
            $this->systems = implode(
              ',',
              array_unique(
                array_map(
                  static fn(System $system) => $system->type->value,
                  $systems
                )
              )
            );
        }
    }

    /** @var numeric[] */
    #[NoDB]
    public array $allowedTeams {
        get => json_decode($this->teams) ?? [];
        /**
         * @param  numeric[]  $teams
         */
        set(array $teams) {
            $this->teams = json_encode($teams);
        }
    }

    public function isSolo() : bool {
        return $this->type === GameModeType::SOLO;
    }

    /**
     * Get winning team by some rules
     *
     * Default rules are: the best position (score) wins.
     *
     * @param  Game  $game
     *
     * @return Player|Team|null null = draw
     * @throws ValidationException
     */
    public function getWin(GameInterface $game) : Player | Team | null {
        if ($this->isTeam()) {
            /** @var TeamCollection $teams */
            $teams = $game->teamsSorted;
            /** @var Team $team */
            $team = $teams->first();
            if (count($teams) === 2 && $team->getScore() === $teams->last()->score) {
                return null;
            }
            return $team;
        }
        /** @var Player $player */
        $player = $game->playersSorted->first();
        return $player;
    }

    public function isTeam() : bool {
        return $this->type === GameModeType::TEAM;
    }

    public function recalculateScores(GameInterface $game) : void {
        $this->recalculateScoresPlayers($game);
        $this->recalculateScoresTeams($game);
    }

    protected function recalculateScoresPlayers(GameInterface $game) : void {
        if (!isset($game->scoring)) {
            return;
        }
        try {
            if ($game instanceof LaserMaxxGameInterface) {
                /** @var Player $player */
                foreach ($game->players as $player) {
                    $player->score =
                      ($player->hits * $game->scoring->hitOther) +
                      ($player->deaths * $game->scoring->deathOther) +
                      ($player->shots * $game->scoring->shot);
                }
            }
        } catch (ValidationException | DirectoryCreationException $e) {
        }
    }

    protected function recalculateScoresTeams(GameInterface $game) : void {
        try {
            /** @var Team $team */
            foreach ($game->teams as $team) {
                $team->score = 0;
                /** @var Player $player */
                foreach ($team->players as $player) {
                    $team->score += $player->score;
                }
            }
        } catch (ValidationException | DirectoryCreationException $e) {
        }
    }

    public function reorderGame(GameInterface $game) : void {
        // Reorder players
        $players = $game->playersSorted;
        $i = 1;
        foreach ($players as $player) {
            $player->position = $i++;
        }

        // Reorder teams
        $teams = $game->teamsSorted;
        $i = 1;
        foreach ($teams as $team) {
            $team->position = $i++;
        }
    }

    /**
     * @return class-string<AbstractMode>
     */
    public function getSoloAlternative() : string {
        return $this::class;
    }

    /**
     * @return class-string<AbstractMode>
     */
    public function getTeamAlternative() : string {
        return $this::class;
    }

    /**
     * @return GameModeVariationValue[][]
     * @throws ValidationException
     * @throws ModelNotFoundException
     */
    public function getVariationsPublic() : array {
        return array_filter(
          $this->variations,
          fn($variationValues) => count($variationValues) > 0
            && first($variationValues)->variation->public
        );
    }

    public function getName() : string {
        return empty($this->alias) ? $this->name : $this->alias;
    }
}

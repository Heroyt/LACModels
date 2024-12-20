<?php

namespace App\GameModels\Game\GameModes;

use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\Enums\GameModeType;
use App\GameModels\Game\Game;
use App\GameModels\Game\ModeSettings;
use App\GameModels\Game\Player;
use App\GameModels\Game\Team;
use App\GameModels\Game\TeamCollection;
use App\Models\GameModeVariation;
use App\Models\GameModeVariationValue;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\Factory;
use Lsr\Core\Models\Attributes\Instantiate;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Attributes\Validation\Required;
use Lsr\Core\Models\Attributes\Validation\StringLength;
use Lsr\Core\Models\Model;
use Lsr\Logging\Exceptions\DirectoryCreationException;

/**
 * Base class for all game mode models
 */
#[PrimaryKey('id_mode')]
#[Factory(GameModeFactory::class)] // @phpstan-ignore-line
abstract class AbstractMode extends Model
{
    public const TABLE = 'game_modes';

    #[Required]
    #[StringLength(1, 20)]
    public string $name        = '';
    public string $alias    = '';
    public ?string $description = '';
    public GameModeType $type        = GameModeType::TEAM;
    public ?string $loadName    = '';
    public string $teams       = '';
    #[Instantiate]
    public ModeSettings $settings;
    public bool $rankable = true;
    public bool $active   = true;
    public bool $public   = true;
    /** @var GameModeVariationValue[][] */
    private array $variations = [];

    public function isSolo(): bool {
        return $this->type === GameModeType::SOLO;
    }

    /**
     * Get winning team by some rules
     *
     * Default rules are: the best position (score) wins.
     *
     * @param Game $game
     *
     * @return Player|Team|null null = draw
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function getWin(Game $game): Player|Team|null {
        if ($this->isTeam()) {
            /** @var Team[]|TeamCollection $teams */
            $teams = $game->getTeamsSorted();
            /** @var Team $team */
            $team = $teams->first();
            if (count($teams) === 2 && $team->getScore() === $teams->last()?->getScore()) {
                return null;
            }
            return $team;
        }
        /** @var Player $player */
        $player = $game->getPlayersSorted()->first();
        return $player;
    }

    public function isTeam(): bool {
        return $this->type === GameModeType::TEAM;
    }

    public function recalculateScores(Game $game): void {
        $this->recalculateScoresPlayers($game);
        $this->recalculateScoresTeams($game);
    }

    protected function recalculateScoresPlayers(Game $game): void {
        if (!isset($game->scoring)) {
            return;
        }
        try {
            /** @var Player $player */
            foreach ($game->getPlayers() as $player) {
                $player->score =
                    ($player->hits * $game->scoring->hitOther) +
                    ($player->deaths * $game->scoring->deathOther) +
                    ($player->shots * $game->scoring->shot);
            }
        } catch (ModelNotFoundException | ValidationException | DirectoryCreationException $e) {
        }
    }

    protected function recalculateScoresTeams(Game $game): void {
        try {
            /** @var Team $team */
            foreach ($game->getTeams() as $team) {
                $team->score = 0;
                /** @var Player $player */
                foreach ($team->getPlayers() as $player) {
                    $team->score += $player->score;
                }
            }
        } catch (ModelNotFoundException | ValidationException | DirectoryCreationException $e) {
        }
    }

    public function reorderGame(Game $game): void {
        // Reorder players
        $players = $game->getPlayersSorted();
        $i = 1;
        foreach ($players as $player) {
            $player->position = $i++;
        }

        // Reorder teams
        $teams = $game->getTeamsSorted();
        $i = 1;
        foreach ($teams as $team) {
            $team->position = $i++;
        }
    }

    /**
     * @return class-string<AbstractMode>
     */
    public function getSoloAlternative(): string {
        return $this::class;
    }

    /**
     * @return class-string<AbstractMode>
     */
    public function getTeamAlternative(): string {
        return $this::class;
    }

    /**
     * @return GameModeVariationValue[][]
     * @throws DirectoryCreationException
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function getVariations(): array {
        if (empty($this->variations)) {
            $rows = DB::select(GameModeVariation::TABLE_VALUES, '[id_variation], [value], [suffix], [order]')
                ->where('[id_mode] = %i', $this->id)
                ->orderBy('[id_variation], [order]')
                ->cacheTags('mode.variations', 'mode.' . $this->id, 'mode.' . $this->id . '.variations')
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

    /**
     * @return GameModeVariationValue[][]
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function getVariationsPublic(): array {
        $public = [];
        foreach ($this->getVariations() as $id => $variationValues) {
            if (count($variationValues) > 0 && first($variationValues)->variation->public) {
                $public[$id] = $variationValues;
            }
        }
        return $public;
    }

    public function getName(): string {
        return empty($this->alias) ? $this->name : $this->alias;
    }
}

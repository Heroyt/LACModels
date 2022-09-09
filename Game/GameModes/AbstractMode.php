<?php

namespace App\GameModels\Game\GameModes;

use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\Enums\GameModeType;
use App\GameModels\Game\Game;
use App\GameModels\Game\ModeSettings;
use App\GameModels\Game\Player;
use App\GameModels\Game\Team;
use App\GameModels\Game\TeamCollection;
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
	public string       $name        = '';
	public ?string      $description = '';
	public GameModeType $type        = GameModeType::TEAM;
	#[Instantiate]
	public ModeSettings $settings;


	public function isSolo() : bool {
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
	public function getWin(Game $game) : Player|Team|null {
		if ($this->isTeam()) {
			/** @var Team[]|TeamCollection $teams */
			$teams = $game->getTeamsSorted();
			/** @var Team $team */
			$team = $teams->first();
			if (count($teams) === 2 && $team->score === $teams->last()?->score) {
				return null;
			}
			return $team;
		}
		/** @var Player $player */
		$player = $game->getPlayersSorted()->first();
		return $player;
	}

	public function isTeam() : bool {
		return $this->type === GameModeType::TEAM;
	}

	public function recalculateScores(Game $game) : void {
		$this->recalculateScoresPlayers($game);
		$this->recalculateScoresTeams($game);
	}

	public function reorderGame(Game $game) : void {
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

	protected function recalculateScoresPlayers(Game $game) : void {
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
		} catch (ModelNotFoundException|ValidationException|DirectoryCreationException $e) {
		}
	}

	protected function recalculateScoresTeams(Game $game) : void {
		try {
			/** @var Team $team */
			foreach ($game->getTeams() as $team) {
				$team->score = 0;
				/** @var Player $player */
				foreach ($team->getPlayers() as $player) {
					$team->score += $player->score;
				}
			}
		} catch (ModelNotFoundException|ValidationException|DirectoryCreationException $e) {
		}
	}


}
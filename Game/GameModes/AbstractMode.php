<?php

namespace App\GameModels\Game\GameModes;

use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\Enums\GameModeType;
use App\GameModels\Game\Game;
use App\GameModels\Game\ModeSettings;
use App\GameModels\Game\Player;
use App\GameModels\Game\Team;
use App\GameModels\Game\TeamCollection;
use Lsr\Core\Models\Attributes\Factory;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;

#[PrimaryKey('id_mode')]
#[Factory(GameModeFactory::class)]
abstract class AbstractMode extends Model
{

	public const TABLE = 'game_modes';

	public const DEFINITION = [
		'name'        => ['validators' => ['required']],
		'description' => ['noTest' => true],
		'type'        => ['class' => GameModeType::class],
		'settings'    => ['noTest' => true, 'class' => ModeSettings::class, 'initialize' => true],
	];

	public string       $name        = '';
	public ?string      $description = '';
	public GameModeType $type        = GameModeType::TEAM;
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


}
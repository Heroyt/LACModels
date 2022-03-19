<?php

namespace App\GameModels\Game\GameModes;

use App\Core\AbstractModel;
use App\Core\Interfaces\InsertExtendInterface;
use App\Exceptions\GameModeNotFoundException;
use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\Enums\GameModeType;
use App\GameModels\Game\Game;
use App\GameModels\Game\ModeSettings;
use App\GameModels\Game\Player;
use App\GameModels\Game\Team;
use App\GameModels\Game\TeamCollection;
use Dibi\Row;

abstract class AbstractMode extends AbstractModel implements InsertExtendInterface
{

	public const TABLE       = 'game_modes';
	public const PRIMARY_KEY = 'id_mode';

	public const DEFINITION = [
		'name'        => ['validators' => ['required']],
		'description' => [],
		'type'        => ['class' => GameModeType::class],
		'settings'    => ['class' => ModeSettings::class, 'initialize' => true],
	];

	public string       $name        = '';
	public ?string      $description = '';
	public GameModeType $type        = GameModeType::TEAM;
	public ModeSettings $settings;

	/**
	 * @param Row $row
	 *
	 * @return InsertExtendInterface
	 * @throws GameModeNotFoundException
	 */
	public static function parseRow(Row $row) : InsertExtendInterface {
		return GameModeFactory::getById($row->id_mode ?? 0);
	}


	public function addQueryData(array &$data) : void {
		$data['id_mode'] = $this->id;
	}

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
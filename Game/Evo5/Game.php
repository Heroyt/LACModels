<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game\Evo5;

use App\Exceptions\GameModeNotFoundException;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\Enums\GameModeType;
use App\GameModels\Game\Evo5\GameModes\Deathmach;
use App\GameModels\Game\Evo5\GameModes\TeamDeathmach;
use App\GameModels\Game\Player as BasePlayer;
use Dibi\Row;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\Factory;
use Lsr\Core\Models\Attributes\NoDB;
use Lsr\Core\Models\Attributes\PrimaryKey;

/**
 * LaserMaxx Evo5 game model
 *
 * @extends \App\GameModels\Game\Game<Team, Player>
 */
#[PrimaryKey('id_game')]
#[Factory(GameFactory::class, ['system' => 'evo5'])] // @phpstan-ignore-line
class Game extends \App\GameModels\Game\Game
{

	public const SYSTEM = 'evo5';
	public const TABLE  = 'evo5_games';

	public int    $fileNumber;
	public string $modeName;
	/** @var int Initial lives */
	public int $lives = 9999;
	/** @var int Initial ammo count */
	public int $ammo = 9999;
	/** @var int Respawn time in seconds */
	public int $respawn = 5;

	#[NoDB]
	public string  $playerClass = Player::class;
	#[NoDB]
	public string  $teamClass   = Team::class;
	protected bool $minesOn;

	public function __construct(?int $id = null, ?Row $dbRow = null) {
		parent::__construct($id, $dbRow);
		if (!isset($this->mode) && !empty($this->modeName) && !empty($this->gameType)) {
			try {
				$this->mode = GameModeFactory::find($this->modeName, $this->gameType, $this::SYSTEM);
				if (!isset($this->mode)) {
					$this->mode = $this->gameType === GameModeType::TEAM ? new TeamDeathmach() : new Deathmach();
				}
			} catch (GameModeNotFoundException) {
			}
		}
	}

	/**
	 * @return string[]
	 */
	public static function getTeamColors() : array {
		return [
			0 => '#f00',
			1 => '#0c0',
			2 => '#00f',
			3 => '#f081da',
			4 => '#f5bc00',
			5 => '#28d1f0',
		];
	}

	/**
	 * @return string[]
	 */
	public static function getTeamNames() : array {
		return [
			0 => lang('Red team', context: 'team.names'),
			1 => lang('Green team', context: 'team.names'),
			2 => lang('Blue team', context: 'team.names'),
			3 => lang('Pink team', context: 'team.names'),
			4 => lang('Yellow team', context: 'team.names'),
			5 => lang('Ocean team', context: 'team.names'),
		];
	}

	public function insert() : bool {
		$this->logger->info('Inserting game: '.$this->fileNumber);
		return parent::insert();
	}

	public function save() : bool {
		return parent::save() && $this->saveTeams() && $this->savePlayers();
	}

	/**
	 * @return array|string[]
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public function getBestsFields() : array {
		$info = parent::getBestsFields();
		if ($this->mode?->isTeam()) {
			if ($this->mode->settings->bestHitsOwn) {
				$info['hitsOwn'] = lang('Zabiják vlastního týmu', context: 'results.bests');
			}
			if ($this->mode->settings->bestDeathsOwn) {
				$info['deathsOwn'] = lang('Největší vlastňák', context: 'results.bests');
			}
		}
		if ($this->mode?->settings->bestMines && $this->mode->settings->mines && $this->isMinesOn()) {
			$info['mines'] = lang('Drtič min', context: 'results.bests');
		}
		return $info;
	}

	/**
	 * Check if mines were enabled
	 *
	 * Checks players until it finds one with some mine-related scores.
	 *
	 * @return bool
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public function isMinesOn() : bool {
		if (!isset($this->minesOn)) {
			$this->minesOn = false;
			/** @var Player $player */
			foreach ($this->getPlayers() as $player) {
				if ($player->minesHits !== 0 || $player->scoreMines !== 0 || $player->bonus->getSum() > 0) {
					$this->minesOn = true;
					break;
				}
			}
		}
		return $this->minesOn;
	}

	public function getBestPlayer(string $property) : ?BasePlayer {
		if ($property === 'mines' && !$this->isMinesOn()) {
			return null;
		}
		return parent::getBestPlayer($property);
	}
}
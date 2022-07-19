<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game;

use App\GameModels\Factory\TeamFactory;
use App\GameModels\Traits\WithGame;
use App\GameModels\Traits\WithPlayers;
use Lsr\Core\App;
use Lsr\Core\Caching\Cache;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\Factory;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Attributes\Validation\Required;
use Lsr\Core\Models\Attributes\Validation\StringLength;
use Lsr\Core\Models\Model;

/**
 * Base class for Team models
 */
#[PrimaryKey('id_team')]
#[Factory(TeamFactory::class)]
abstract class Team extends Model
{
	use WithPlayers;
	use WithGame;

	public const PRIMARY_KEY = 'id_team';

	#[Required]
	public int    $color;
	#[Required]
	public int    $score;
	#[Required]
	public int    $position;
	#[Required]
	#[StringLength(1, 15)]
	public string $name;


	public function save() : bool {
		/** @var int|null $test */
		$test = DB::select($this::TABLE, $this::getPrimaryKey())->where('id_game = %i && name = %s', $this->game?->id, $this->name)->fetchSingle();
		if (isset($test)) {
			$this->id = $test;
		}
		return parent::save();
	}

	/**
	 * @return int
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public function getDeaths() : int {
		$sum = 0;
		foreach ($this->getPlayers() as $player) {
			$sum += $player->deaths;
		}
		return $sum;
	}

	/**
	 * @return float
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public function getAccuracy() : float {
		return $this->getShots() === 0 ? 0 : round(100 * $this->getHits() / $this->getShots(), 2);
	}

	/**
	 * @return int
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public function getShots() : int {
		$sum = 0;
		foreach ($this->getPlayers() as $player) {
			$sum += $player->shots;
		}
		return $sum;
	}

	/**
	 * @return int
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public function getHits() : int {
		$sum = 0;
		foreach ($this->getPlayers() as $player) {
			$sum += $player->hits;
		}
		return $sum;
	}

	/**
	 * @param Team $team
	 *
	 * @return int
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public function getHitsTeam(Team $team) : int {
		$sum = 0;
		foreach ($this->getPlayers() as $player) {
			foreach ($player->getHitsPlayers() as $hits) {
				if ($hits->playerTarget->getTeam()?->color === $team->color) {
					$sum += $hits->count;
				}
			}
		}
		return $sum;
	}

	public function getTeamBgClass(bool $includeSystem = false) : string {
		return 'team-'.($includeSystem ? $this->getGame()::SYSTEM.'-' : '').$this->color;
	}

	/**
	 * @return int
	 */
	public function getTeamColor() : int {
		return $this->color;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function jsonSerialize() : array {
		$data = parent::jsonSerialize();
		if (isset($data['players'])) {
			unset($data['players']);
		}
		if (isset($data['game'])) {
			unset($data['game']);
		}
		return $data;
	}

	public function update() : bool {
		// Invalidate cache
		/** @var Cache $cache */
		$cache = App::getService('cache');
		$cache->remove('teams/'.$this->getGame()::SYSTEM.'/'.$this->id);
		return parent::update();
	}

	public function delete() : bool {
		// Invalidate cache
		/** @var Cache $cache */
		$cache = App::getService('cache');
		$cache->remove('teams/'.$this->getGame()::SYSTEM.'/'.$this->id);
		return parent::delete();
	}

}
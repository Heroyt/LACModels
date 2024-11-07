<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game;

use App\Exceptions\GameModeNotFoundException;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Throwable;

/**
 * A trophy (achievement) which a player can obtain
 */
class PlayerTrophy
{
	public const SPECIAL_TROPHIES = [
		'100-percent',
		'zero-deaths',
		'devil',
		'not-found',
		'not-found-shots',
	];
	public const OTHER_TROPHIES   = [
		'kd-1',
		'kd-2',
		'50-percent',
		'5-percent',
		'kd-0-5',
		'fair',
	];

	public const RARE_TROPHIES = [
		'zero',
		'team-50',
		'favouriteTarget',
		'favouriteTargetOf',
	];

	/**
	 * @var array{name:string,description:string,icon:string}[] Best names
	 */
	public static array $fields;
	public bool         $solo;

	/** @var array<string,bool> */
	private array $checked = [];

	/**
	 * @param Player $player
	 *
	 * @throws Throwable
	 * @throws GameModeNotFoundException
	 */
	public function __construct(private readonly Player $player) {
		$this->solo = $player->getGame()->getMode()?->isSolo() ?? false;
		self::getFields(); // Initialize fields array
	}

	/**
	 * Get all available trophies
	 *
	 * @return array{name:string,description:string,icon:string}[]
	 */
	public static function getFields(): array {
		if (empty(self::$fields)) {
			self::$fields = [
				'score'             => [
					'name'        => lang('Absolutní vítěz', context: 'results.bests'),
					'description' => lang('Staň se hráčem s největším skóre.', context: 'results.bests.description'),
					'icon'        => 'crown',
				],
				'hits'              => [
					'name'        => lang('Největší terminátor', context: 'results.bests'),
					'description' => lang(
						         'Staň se hráčem s největším počtem zásahů.',
						context: 'results.bests.description'
					),
					'icon'        => 'predator',
				],
				'deaths'            => [
					'name'        => lang('Objekt největšího zájmu', context: 'results.bests'),
					'description' => lang(
						         'Staň se hráčem s největším počtem smrtí.',
						context: 'results.bests.description'
					),
					'icon'        => 'skull',
				],
				'accuracy'          => [
					'name'        => lang('Hráč s nejlepší muškou', context: 'results.bests'),
					'description' => lang('Staň se nejpřesnějším hráčem.', context: 'results.bests.description'),
					'icon'        => 'target',
				],
				'shots'             => [
					'name'        => lang('Nejúspornější střelec', context: 'results.bests'),
					'description' => lang(
						         'Staň se hráčem s nejméně výstřely z celé hry.',
						context: 'results.bests.description'
					),
					'icon'        => 'bullet',
				],
				'miss'              => [
					'name'        => lang('Největší mimoň', context: 'results.bests'),
					'description' => lang(
						         'Staň se hráčem, který se nejvícekrát netrefil.',
						context: 'results.bests.description'
					),
					'icon'        => 'bullets',
				],
				'hitsOwn'           => [
					'name'        => lang('Zabiják vlastního týmu', context: 'results.bests'),
					'description' => lang(
						         'Staň se hrářem, který nejvíckát zasáhnul spoluhráče.',
						context: 'results.bests.description'
					),
					'icon'        => 'kill',
				],
				'deathsOwn'         => [
					'name'        => lang('Největší vlastňák', context: 'results.bests'),
					'description' => lang(
						         'Staň se hráčem, kterého nejvíckrát trefili spoluhráči.',
						context: 'results.bests.description'
					),
					'icon'        => 'skull',
				],
				'mines'             => [
					'name'        => lang('Drtič min', context: 'results.bests'),
					'description' => lang('Získej nejvíce bonusů za hru.', context: 'results.bests.description'),
					'icon'        => 'base_2',
				],
				'zero-deaths'       => [
					'name'        => lang('Nedotknutelný', context: 'results.bests'),
					'description' => lang('Zemři méně než 10krát za hru.', context: 'results.bests.description'),
					'icon'        => 'shield',
				],
				'100-percent'       => [
					'name'        => lang('Sniper', context: 'results.bests'),
					'description' => lang('Získej přesnost alespoň 95% za hru.', context: 'results.bests.description'),
					'icon'        => 'target',
				],
				'50-percent'        => [
					'name'        => lang('Poloviční sniper', context: 'results.bests'),
					'description' => lang('Získej přesnost alespoň 50% za hru.', context: 'results.bests.description'),
					'icon'        => 'target',
				],
				'5-percent'         => [
					'name'        => lang('Občas se i trefí', context: 'results.bests'),
					'description' => lang('Získej přesnost maximálně 5% za hru.', context: 'results.bests.description'),
					'icon'        => 'target',
				],
				'kd-1'              => [
					'name'        => lang('Vyrovnaný', context: 'results.bests'),
					'description' => lang('Měj téměř stejně zásahů a smrtí.', context: 'results.bests.description'),
					'icon'        => 'balance',
				],
				'kd-2'              => [
					'name'        => lang('Zabiják', context: 'results.bests'),
					'description' => lang(
						         'Měj alespoň 2x tolik zásahů co smrtí.',
						context: 'results.bests.description'
					),
					'icon'        => 'kill',
				],
				'kd-0-5'            => [
					'name'        => lang('Terč', context: 'results.bests'),
					'description' => lang(
						         'Měl alespoň přibližně 2x tolik smrtí co zásahů.',
						context: 'results.bests.description'
					),
					'icon'        => 'dead',
				],
				'zero'              => [
					'name'        => lang('Nula', context: 'results.bests'),
					'description' => lang('Měj 0 skóre.', context: 'results.bests.description'),
					'icon'        => 'zero',
				],
				'team-50'           => [
					'name'        => lang('Tahoun týmu', context: 'results.bests'),
					'description' => lang(
						         'Získej alespoň přibližně polovinu skóre celého tvého týmu.',
						context: 'results.bests.description'
					),
					'icon'        => 'star',
				],
				'favouriteTarget'   => [
					'name'        => lang('Zasedlý', context: 'results.bests'),
					'description' => lang(
						         'Alespoň přibližně polovina všech tvých zásahů je jen jeden hráč.',
						context: 'results.bests.description'
					),
					'icon'        => 'death',
				],
				'favouriteTargetOf' => [
					'name'        => lang('Pronásledovaný', context: 'results.bests'),
					'description' => lang(
						         'Alespoň přibližně polovina všech tvých smrtí je jen jeden hráč.',
						context: 'results.bests.description'
					),
					'icon'        => 'death',
				],
				'devil'             => [
					'name'        => lang('Ďábel', context: 'results.bests'),
					'description' => lang('Získej 666 skóre nebo výstřelů.', context: 'results.bests.description'),
					'icon'        => 'devil',
				],
				'not-found'         => [
					'name'        => lang('Skóre nenalezeno', context: 'results.bests'),
					'description' => lang('Získej 404, 4040, nebo 40400 skóre.', context: 'results.bests.description'),
					'icon'        => 'magnifying-glass',
				],
				'not-found-shots'   => [
					'name'        => lang('Výstřely nenalezeny', context: 'results.bests'),
					'description' => lang('Vystřel 404krát.', context: 'results.bests.description'),
					'icon'        => 'magnifying-glass',
				],
				'fair'              => [
					'name'        => lang('Férový hráč', context: 'results.bests'),
					'description' => lang(
						         'Zasáhni všechny své nepřátele stejněkrát (přibližně).',
						context: 'results.bests.description'
					),
					'icon'        => 'balance',
				],
				'average'           => [
					'name'        => lang('Hráč', context: 'results.bests'),
					'description' => lang(
						         'Průměrný hráč. Bohužel na tebe nesedí žádná z trofejí.',
						context: 'results.bests.description'
					),
					'icon'        => 'Vesta',
				],
			];
		}
		return self::$fields;
	}

	/**
	 * Get one trophy that a player obtained
	 *
	 * Trophies are checked in hierarchical order.
	 *
	 * @return array{name:string,description:string,icon:string}
	 * @throws ModelNotFoundException
	 * @throws Throwable
	 * @throws ValidationException
	 */
	public function getOne(): array {
		// Special
		foreach (self::SPECIAL_TROPHIES as $name) {
			if ($this->check($name)) {
				return $this::getFields()[$name];
			}
		}
		// Rare
		foreach (self::RARE_TROPHIES as $name) {
			if ($this->check($name)) {
				return $this::getFields()[$name];
			}
		}
		// Classic
		foreach ($this->player::CLASSIC_BESTS as $name) {
			if ($this->check($name)) {
				return $this::getFields()[$name];
			}
		}
		// Other
		foreach (self::OTHER_TROPHIES as $name) {
			if ($this->check($name)) {
				return $this::getFields()[$name];
			}
		}
		return $this::getFields()['average'];
	}

	/**
	 * Check if the player obtained a trophy by its name
	 *
	 * @param string $name
	 *
	 * @return bool
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 * @throws Throwable
	 */
	public function check(string $name): bool {
		if (isset($this->checked[$name])) {
			return $this->checked[$name];
		}

		// Classic
		if (in_array($name, $this->player::CLASSIC_BESTS, true)) {
			$best = $this->player->getGame()->getBestPlayer($name);
			$this->checked[$name] = isset($best) && $best->id === $this->player->id;
			return $this->checked[$name];
		}
		// Special
		switch ($name) {
			case '100-percent':
				$this->checked[$name] = $this->player->accuracy >= 95;
				return $this->checked[$name];
			case 'zero-deaths':
				$this->checked[$name] = $this->player->deaths < 10;
				return $this->checked[$name];
			case 'devil':
				$this->checked[$name] = $this->player->score === 666 || $this->player->score === 6666 || $this->player->shots === 666;
				return $this->checked[$name];
			case 'not-found':
				$this->checked[$name] = $this->player->score === 404 || $this->player->score === 4040 || $this->player->score === 40400;
				return $this->checked[$name];
			case 'not-found-shots':
				$this->checked[$name] = $this->player->shots === 404;
				return $this->checked[$name];
			case 'team-50':
				$this->checked[$name] = !$this->solo && $this->player->getTeam(
					)?->score !== 0 && $this->player->getTeam()?->getPlayerCount(
						) > 1 && ($this->player->score / $this->player->getTeam()->score) > 0.45;
				return $this->checked[$name];
			case 'kd-1':
				$this->checked[$name] = $this->player->deaths !== 0 && abs(
						($this->player->hits / $this->player->deaths) - 1
					) < 0.1;
				return $this->checked[$name];
			case 'kd-2':
				$this->checked[$name] = $this->player->deaths !== 0 && ($this->player->hits / $this->player->deaths) > 1.9;
				return $this->checked[$name];
			case 'kd-0-5':
				$this->checked[$name] = $this->player->deaths !== 0 && ($this->player->hits / $this->player->deaths) <= 0.65;
				return $this->checked[$name];
			case '50-percent':
				$this->checked[$name] = $this->player->accuracy >= 50;
				return $this->checked[$name];
			case 'zero':
				$this->checked[$name] = $this->player->score === 0;
				return $this->checked[$name];
			case '5-percent':
				$this->checked[$name] = $this->player->accuracy < 6;
				return $this->checked[$name];
			case 'favouriteTarget':
				$favouriteTarget = $this->player->getFavouriteTarget();
				$this->checked[$name] = isset($favouriteTarget) && $this->player->hits !== 0 && $this->player->getHitsPlayer(
						$favouriteTarget
					) / $this->player->hits > 0.45;
				return $this->checked[$name];
			case 'favouriteTargetOf':
				$favouriteTarget = $this->player->getFavouriteTargetOf();
				$this->checked[$name] = isset($favouriteTarget) && $this->player->deaths !== 0 && $favouriteTarget->getHitsPlayer(
						$this->player
					) / $this->player->deaths > 0.45;
				return $this->checked[$name];
			case 'fair':
				$maxDelta = 0;
				$hits = [];
				$sum = 0;
				$count = 0;
				foreach ($this->player->getHitsPlayers() as $hit) {
					if (!$this->solo && $hit->playerTarget->getTeamColor() === $this->player->getTeamColor()) {
						continue; // Skip teammates
					}
					$hits[] = $hit->count;
					$sum += $hit->count;
					$count++;
				}
				if ($count === 0) {
					$this->checked[$name] = false;
					return $this->checked[$name];
				}
				$average = $sum / $count;
				foreach ($hits as $hit) {
					$delta = abs($hit - $average);
					$maxDelta = max($delta, $maxDelta);
				}
				$this->checked[$name] = $maxDelta < 4;
				return $this->checked[$name];
		}
		$this->checked[$name] = false;
		return false;
	}

	/**
	 * @return array{name:string,description:string,icon:string}[]
	 * @throws ModelNotFoundException
	 * @throws Throwable
	 * @throws ValidationException
	 */
	public function getAll(): array {
		$fields = [];
		foreach ($this::getFields() as $name => $field) {
			if ($name === 'average') {
				continue;
			}
			if ($this->check($name)) {
				$fields[$name] = $field;
			}
		}
		return $fields;
	}

}
<?php

namespace App\GameModels\Game;

use App\Exceptions\ModelNotFoundException;
use App\Logging\DirectoryCreationException;

class PlayerTrophy
{

	/**
	 * @var array{name:string,icon:string}[] Best names
	 */
	public array $fields;
	public bool  $solo;

	public function __construct(
		private Player $player
	) {
		$this->solo = $player->getGame()->mode->isSolo();
		$this->fields = [
			'hits'              => [
				'name' => lang('Největší terminátor', context: 'results.bests'),
				'icon' => 'predator',
			],
			'deaths'            => [
				'name' => lang('Objekt největšího zájmu', context: 'results.bests'),
				'icon' => 'skull',
			],
			'score'             => [
				'name' => lang('Absolutní vítěz', context: 'results.bests'),
				'icon' => 'crown',
			],
			'accuracy'          => [
				'name' => lang('Hráč s nejlepší muškou', context: 'results.bests'),
				'icon' => 'target',
			],
			'shots'             => [
				'name' => lang('Nejúspornější střelec', context: 'results.bests'),
				'icon' => 'bullet',
			],
			'miss'              => [
				'name' => lang('Největší mimoň', context: 'results.bests'),
				'icon' => 'bullets',
			],
			'zero-deaths'       => [
				'name' => lang('Nedotknutelný', context: 'results.bests'),
				'icon' => 'shield',
			],
			'100-percent'       => [
				'name' => lang('Sniper', context: 'results.bests'),
				'icon' => 'target',
			],
			'50-percent'        => [
				'name' => lang('Poloviční sniper', context: 'results.bests'),
				'icon' => 'target',
			],
			'5-percent'         => [
				'name' => lang('Občas se i trefí', context: 'results.bests'),
				'icon' => 'target',
			],
			'hitsOwn'           => [
				'name' => lang('Zabiják vlastního týmu', context: 'results.bests'),
				'icon' => 'kill',
			],
			'deathsOwn'         => [
				'name' => lang('Největší vlastňák', context: 'results.bests'),
				'icon' => 'skull',
			],
			'mines'             => [
				'name' => lang('Drtič min', context: 'results.bests'),
				'icon' => 'base_2',
			],
			'average'           => [
				'name' => lang('Hráč', context: 'results.bests'),
				'icon' => 'Vesta',
			],
			'kd-1'              => [
				'name' => lang('Vyrovnaný', context: 'results.bests'),
				'icon' => 'balance',
			],
			'kd-2'              => [
				'name' => lang('Zabiják', context: 'results.bests'),
				'icon' => 'kill',
			],
			'kd-0-5'            => [
				'name' => lang('Terč', context: 'results.bests'),
				'icon' => 'dead',
			],
			'zero'              => [
				'name' => lang('Nula', context: 'results.bests'),
				'icon' => 'zero',
			],
			'team-50'           => [
				'name' => lang('Tahoun týmu', context: 'results.bests'),
				'icon' => 'star',
			],
			'favouriteTarget'   => [
				'name' => lang('Zasedlý', context: 'results.bests'),
				'icon' => 'death',
			],
			'favouriteTargetOf' => [
				'name' => lang('Pronásledovaný', context: 'results.bests'),
				'icon' => 'death',
			],
			'devil'             => [
				'name' => lang('Ďábel', context: 'results.bests'),
				'icon' => 'devil',
			],
			'not-found'         => [
				'name' => lang('Skóre nenalezeno', context: 'results.bests'),
				'icon' => 'magnifying-glass',
			],
			'not-found-shots'   => [
				'name' => lang('Výstřely nenalezeny', context: 'results.bests'),
				'icon' => 'magnifying-glass',
			],
			'fair'              => [
				'name' => lang('Férový hráč', context: 'results.bests'),
				'icon' => 'balance',
			],
		];
	}

	/**
	 * @return array{name:string,icon:string}
	 * @throws ModelNotFoundException
	 * @throws DirectoryCreationException
	 */
	public function getOne() : array {
		// Special
		foreach (['100-percent', 'zero-deaths', 'devil', 'not-found', 'not-found-shots'] as $name) {
			if ($this->check($name)) {
				return $this->fields[$name];
			}
		}
		// Classic
		foreach ($this->player::CLASSIC_BESTS as $name) {
			if ($this->check($name)) {
				return $this->fields[$name];
			}
		}
		// Other
		foreach ([
							 'team-50',
							 'kd-1',
							 'kd-2',
							 '50-percent',
							 'zero',
							 '5-percent',
							 'kd-0-5',
							 'favouriteTarget',
							 'favouriteTargetOf',
							 'fair',
						 ] as $name) {
			if ($this->check($name)) {
				return $this->fields[$name];
			}
		}
		return $this->fields['average'];
	}

	/**
	 * @param string $name
	 *
	 * @return bool
	 * @throws ModelNotFoundException
	 * @throws DirectoryCreationException
	 */
	private function check(string $name) : bool {
		// Classic
		if (in_array($name, $this->player::CLASSIC_BESTS, true)) {
			$best = $this->player->getGame()->getBestPlayer($name);
			return isset($best) && $best->id === $this->player->id;
		}
		// Special
		switch ($name) {
			case '100-percent':
				return $this->player->accuracy >= 95;
			case 'zero-deaths':
				return $this->player->deaths < 10;
			case 'devil':
				return $this->player->score === 666 || $this->player->score === 6666 || $this->player->shots === 666;
			case 'not-found':
				return $this->player->score === 404 || $this->player->score === 4040 || $this->player->score === 40400;
			case 'not-found-shots':
				return $this->player->shots === 404;
			case 'team-50':
				return !$this->solo && ($this->player->score / $this->player->getTeam()->score) > 0.45;
			case 'kd-1':
				return abs(($this->player->hits / $this->player->deaths) - 1) < 0.1;
			case 'kd-2':
				return ($this->player->hits / $this->player->deaths) > 1.9;
			case 'kd-0-5':
				return abs(($this->player->hits / $this->player->deaths) - 0.5) < 0.15;
			case '50-percent':
				return $this->player->accuracy >= 50;
			case 'zero':
				return $this->player->score === 0;
			case '5-percent':
				return $this->player->accuracy < 6;
			case 'favouriteTarget':
				$favouriteTarget = $this->player->getFavouriteTarget();
				return isset($favouriteTarget) && $this->player->getHitsPlayer($favouriteTarget) / $this->player->hits > 0.45;
			case 'favouriteTargetOf':
				$favouriteTarget = $this->player->getFavouriteTargetOf();
				return isset($favouriteTarget) && $favouriteTarget->getHitsPlayer($this->player) / $this->player->deaths > 0.45;
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
				$average = $sum / $count;
				foreach ($hits as $hit) {
					$delta = abs($hit - $average);
					$maxDelta = max($delta, $maxDelta);
				}
				return $maxDelta < 4;
		}
		return false;
	}

	/**
	 * @return array{name:string,icon:string}[]
	 * @throws ModelNotFoundException
	 * @throws DirectoryCreationException
	 */
	public function getAll() : array {
		$fields = [];
		foreach ($this->fields as $name => $field) {
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
<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game;

use App\Core\App;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Throwable;

/**
 * A trophy (achievement) which a player can obtain
 */
class PlayerTrophy
{
    public const array SPECIAL_TROPHIES = [
      '100-percent',
      'zero-deaths',
      'devil',
      'not-found',
      'not-found-shots',
    ];
    public const array OTHER_TROPHIES = [
      'kd-1',
      'kd-2',
      '50-percent',
      '5-percent',
      'kd-0-5',
      'fair',
    ];

    public const array RARE_TROPHIES = [
      'zero',
      'team-50',
      'favouriteTarget',
      'favouriteTargetOf',
    ];

    /**
     * @var array{name:string,description:string,icon:string}[][] Best names
     */
    public static array $fields = [];
    public bool $solo;

    /**
     * @template P of Player
     * @param  P  $player
     */
    public function __construct(
      private readonly Player $player
    ) {
        $this->solo = $player->game->mode?->isSolo() ?? false;
        self::getFields(); // Initialize fields array
    }

    /**
     * Get all available trophies
     *
     * @return array{name:string,description:string,icon:string}[]
     */
    public static function getFields() : array {
        $lang = App::getInstance()->translations->getLang();
        if (empty(self::$fields[$lang])) {
            self::$fields[$lang] = [
              'score'             => [
                'name'        => lang('Absolutní vítěz', context: 'bests', domain: 'results'),
                'description' => lang(
                           'Staň se hráčem s největším skóre.',
                  context: 'bests.description',
                  domain : 'results'
                ),
                'icon'        => 'crown',
              ],
              'hits'              => [
                'name'        => lang('Největší terminátor', context: 'bests', domain: 'results'),
                'description' => lang(
                           'Staň se hráčem s největším počtem zásahů.',
                  context: 'bests.description',
                  domain : 'results'
                ),
                'icon'        => 'predator',
              ],
              'deaths'            => [
                'name'        => lang('Objekt největšího zájmu', context: 'bests', domain: 'results'),
                'description' => lang(
                           'Staň se hráčem s největším počtem smrtí.',
                  context: 'bests.description',
                  domain : 'results'
                ),
                'icon'        => 'skull',
              ],
              'accuracy'          => [
                'name'        => lang('Hráč s nejlepší muškou', context: 'bests', domain: 'results'),
                'description' => lang('Staň se nejpřesnějším hráčem.', context: 'bests.description', domain: 'results'),
                'icon'        => 'target',
              ],
              'shots'             => [
                'name'        => lang('Nejúspornější střelec', context: 'bests', domain: 'results'),
                'description' => lang(
                           'Staň se hráčem s nejméně výstřely z celé hry.',
                  context: 'bests.description',
                  domain : 'results'
                ),
                'icon'        => 'bullet',
              ],
              'miss'              => [
                'name'        => lang('Největší mimoň', context: 'bests', domain: 'results'),
                'description' => lang(
                           'Staň se hráčem, který se nejvícekrát netrefil.',
                  context: 'bests.description',
                  domain : 'results'
                ),
                'icon'        => 'bullets',
              ],
              'hitsOwn'           => [
                'name'        => lang('Zabiják vlastního týmu', context: 'bests', domain: 'results'),
                'description' => lang(
                           'Staň se hrářem, který nejvíckát zasáhnul spoluhráče.',
                  context: 'bests.description',
                  domain : 'results'
                ),
                'icon'        => 'kill',
              ],
              'deathsOwn'         => [
                'name'        => lang('Největší vlastňák', context: 'bests', domain: 'results'),
                'description' => lang(
                           'Staň se hráčem, kterého nejvíckrát trefili spoluhráči.',
                  context: 'bests.description',
                  domain : 'results'
                ),
                'icon'        => 'skull',
              ],
              'mines'             => [
                'name'        => lang('Drtič min', context: 'bests', domain: 'results'),
                'description' => lang('Získej nejvíce bonusů za hru.', context: 'bests.description', domain: 'results'),
                'icon'        => 'base_2',
              ],
              'zero-deaths'       => [
                'name'        => lang('Nedotknutelný', context: 'bests', domain: 'results'),
                'description' => lang('Zemři méně než 10krát za hru.', context: 'bests.description', domain: 'results'),
                'icon'        => 'shield',
              ],
              '100-percent'       => [
                'name'        => lang('Sniper', context: 'bests', domain: 'results'),
                'description' => lang(
                           'Získej přesnost alespoň 95% za hru.',
                  context: 'bests.description',
                  domain : 'results'
                ),
                'icon'        => 'target',
              ],
              '50-percent'        => [
                'name'        => lang('Poloviční sniper', context: 'bests', domain: 'results'),
                'description' => lang(
                           'Získej přesnost alespoň 50% za hru.',
                  context: 'bests.description',
                  domain : 'results'
                ),
                'icon'        => 'target',
              ],
              '5-percent'         => [
                'name'        => lang('Občas se i trefí', context: 'bests', domain: 'results'),
                'description' => lang(
                           'Získej přesnost maximálně 5% za hru.',
                  context: 'bests.description',
                  domain : 'results'
                ),
                'icon'        => 'target',
              ],
              'kd-1'              => [
                'name'        => lang('Vyrovnaný', context: 'bests', domain: 'results'),
                'description' => lang(
                           'Měj téměř stejně zásahů a smrtí.',
                  context: 'bests.description',
                  domain : 'results'
                ),
                'icon'        => 'balance',
              ],
              'kd-2'              => [
                'name'        => lang('Zabiják', context: 'bests', domain: 'results'),
                'description' => lang(
                           'Měj alespoň 2x tolik zásahů co smrtí.',
                  context: 'bests.description',
                  domain : 'results'
                ),
                'icon'        => 'kill',
              ],
              'kd-0-5'            => [
                'name'        => lang('Terč', context: 'bests', domain: 'results'),
                'description' => lang(
                           'Měl alespoň přibližně 2x tolik smrtí co zásahů.',
                  context: 'bests.description',
                  domain : 'results'
                ),
                'icon'        => 'dead',
              ],
              'zero'              => [
                'name'        => lang('Nula', context: 'bests', domain: 'results'),
                'description' => lang('Měj 0 skóre.', context: 'bests.description', domain: 'results'),
                'icon'        => 'zero',
              ],
              'team-50'           => [
                'name'        => lang('Tahoun týmu', context: 'bests', domain: 'results'),
                'description' => lang(
                           'Získej alespoň přibližně polovinu skóre celého tvého týmu.',
                  context: 'bests.description',
                  domain : 'results'
                ),
                'icon'        => 'star',
              ],
              'favouriteTarget'   => [
                'name'        => lang('Zasedlý', context: 'bests', domain: 'results'),
                'description' => lang(
                           'Alespoň přibližně polovina všech tvých zásahů je jen jeden hráč.',
                  context: 'bests.description',
                  domain : 'results'
                ),
                'icon'        => 'death',
              ],
              'favouriteTargetOf' => [
                'name'        => lang('Pronásledovaný', context: 'bests', domain: 'results'),
                'description' => lang(
                           'Alespoň přibližně polovina všech tvých smrtí je jen jeden hráč.',
                  context: 'bests.description',
                  domain : 'results'
                ),
                'icon'        => 'death',
              ],
              'devil'             => [
                'name'        => lang('Ďábel', context: 'bests', domain: 'results'),
                'description' => lang(
                           'Získej 666 skóre nebo výstřelů.',
                  context: 'bests.description',
                  domain : 'results'
                ),
                'icon'        => 'devil',
              ],
              'not-found'         => [
                'name'        => lang('Skóre nenalezeno', context: 'bests', domain: 'results'),
                'description' => lang(
                           'Získej 404, 4040, nebo 40400 skóre.',
                  context: 'bests.description',
                  domain : 'results'
                ),
                'icon'        => 'magnifying-glass',
              ],
              'not-found-shots'   => [
                'name'        => lang('Výstřely nenalezeny', context: 'bests', domain: 'results'),
                'description' => lang('Vystřel 404krát.', context: 'bests.description', domain: 'results'),
                'icon'        => 'magnifying-glass',
              ],
              'fair'              => [
                'name'        => lang('Férový hráč', context: 'bests', domain: 'results'),
                'description' => lang(
                           'Zasáhni všechny své nepřátele stejněkrát (přibližně).',
                  context: 'bests.description',
                  domain : 'results'
                ),
                'icon'        => 'balance',
              ],
              'average'           => [
                'name'        => lang('Hráč', context: 'bests', domain: 'results'),
                'description' => lang(
                           'Průměrný hráč. Bohužel na tebe nesedí žádná z trofejí.',
                  context: 'bests.description',
                  domain : 'results'
                ),
                'icon'        => 'Vesta',
              ],
            ];
        }
        return self::$fields[$lang];
    }

    /**
     * Get one trophy that a player obtained
     *
     * Trophies are checked in hierarchical order.
     *
     * @return array{name:string,description:string,icon:string}
     * @throws DirectoryCreationException
     * @throws ValidationException
     */
    public function getOne() : array {
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
     * @param  string  $name
     *
     * @return bool
     * @throws ValidationException
     * @throws DirectoryCreationException
     * @throws Throwable
     */
    public function check(string $name) : bool {
        // Classic
        if (in_array($name, $this->player::CLASSIC_BESTS, true)) {
            $best = $this->player->game->getBestPlayer($name);
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
                return !$this->solo && $this->player->score > 0 && $this->player->team?->score !== 0 && $this->player->team?->playerCount > 1 && ($this->player->score / $this->player->team->score) > 0.45;
            case 'kd-1':
                return $this->player->deaths !== 0 && abs(($this->player->hits / $this->player->deaths) - 1) < 0.1;
            case 'kd-2':
                return $this->player->deaths !== 0 && ($this->player->hits / $this->player->deaths) > 1.9;
            case 'kd-0-5':
                return $this->player->deaths !== 0 && ($this->player->hits / $this->player->deaths) <= 0.65;
            case '50-percent':
                return $this->player->accuracy >= 50;
            case 'zero':
                return $this->player->score === 0;
            case '5-percent':
                return $this->player->accuracy < 6;
            case 'favouriteTarget':
                $favouriteTarget = $this->player->favouriteTarget;
                return isset($favouriteTarget) && $this->player->hits !== 0 && $this->player->getHitsPlayer(
                    $favouriteTarget
                  ) / $this->player->hits > 0.45;
            case 'favouriteTargetOf':
                $favouriteTarget = $this->player->favouriteTargetOf;
                return isset($favouriteTarget) && $this->player->deaths !== 0 && $favouriteTarget->getHitsPlayer(
                    $this->player
                  ) / $this->player->deaths > 0.45;
            case 'fair':
                $maxDelta = 0;
                $hits = [];
                $sum = 0;
                $count = 0;
                foreach ($this->player->getHitsPlayers() as $hit) {
                    if (!$this->solo && $hit->playerTarget->color === $this->player->color) {
                        continue; // Skip teammates
                    }
                    $hits[] = $hit->count;
                    $sum += $hit->count;
                    $count++;
                }
                if ($count === 0) {
                    return false;
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
     * @return array{name:string,description:string,icon:string}[]
     * @throws DirectoryCreationException
     * @throws ValidationException
     */
    public function getAll() : array {
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

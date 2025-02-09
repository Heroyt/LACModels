<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game;

use App\Core\App;
use App\Exceptions\GameModeNotFoundException;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\GameModes\AbstractMode;
use App\GameModels\Traits\Expandable;
use App\GameModels\Traits\WithPlayers;
use App\GameModels\Traits\WithTeams;
use App\Models\BaseModel;
use App\Models\GameGroup;
use App\Models\MusicMode;
use App\Models\WithMetaData;
use App\Services\FeatureConfig;
use App\Services\LaserLiga\LigaApi;
use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Dibi\Row;
use JsonException;
use LAC\Modules\Tables\Models\Table;
use LAC\Modules\Tournament\Models\Game as TournamentGame;
use Lsr\Caching\Cache;
use Lsr\Core\Config;
use Lsr\Db\DB;
use Lsr\Helpers\Tools\Strings;
use Lsr\Lg\Results\Collections\CollectionCompareFilter;
use Lsr\Lg\Results\Enums\Comparison;
use Lsr\Lg\Results\Enums\GameModeType;
use Lsr\Lg\Results\Interface\Models\GameGroupInterface;
use Lsr\Lg\Results\Interface\Models\GameInterface;
use Lsr\Lg\Results\Interface\Models\GameModeInterface;
use Lsr\Lg\Results\Interface\Models\MusicModeInterface;
use Lsr\Lg\Results\LaserMaxx\Evo5\BonusCounts;
use Lsr\Lg\Results\LaserMaxx\Evo5\Scoring;
use Lsr\Lg\Results\Timing;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\ObjectValidation\Attributes\NoValidate;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\Instantiate;
use Lsr\Orm\Attributes\NoDB;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\LoadingType;
use Nette\Caching\Cache as CacheParent;
use OpenApi\Attributes as OA;
use Throwable;

/**
 * Base class for game models
 *
 * @phpstan-type GameMeta array{
 *      music:null|numeric,
 *      mode:string,
 *      loadTime:int,
 *      group?:int,
 *      table?:int,
 *      variations?:array<int,string>,
 *      hash: string,
 * }
 *
 * @property Table|null $table
 * @property TournamentGame|null $tournamentGame
 * @phpstan-consistent-constructor
 * @template T of Team
 * @template P of Player
 *
 * @phpstan-use WithTeams<T>
 * @use WithTeams<T>
 * @phpstan-use WithPlayers<P>
 * @use WithPlayers<P>
 * @phpstan-use WithMetaData<GameMeta>
 * @use WithMetaData<GameMeta>
 */
#[PrimaryKey('id_game')]
#[OA\Schema(schema: 'Game')]
#[Factory(GameFactory::class)] // @phpstan-ignore-line
abstract class Game extends BaseModel implements GameInterface
{
    use WithPlayers;
    use WithTeams;
    use Expandable;
    use WithMetaData;

    /** @var 'evo5'|'evo6'|'laserforce'|string */
    public const string SYSTEM = '';
    public const array CACHE_TAGS = ['games'];

    public const string DI_TAG = 'gameDataExtension';
    private static string $codePrefix;

    #[OA\Property]
    public ?string $resultsFile = null;
    #[OA\Property]
    public string $modeName;
    #[OA\Property]
    public ?DateTimeInterface $fileTime = null;
    #[OA\Property]
    public ?DateTimeInterface $start = null;
    #[OA\Property]
    public ?DateTimeInterface $importTime = null;
    #[OA\Property]
    public ?DateTimeInterface $end = null;
    #[Instantiate, OA\Property]
    public ?Timing $timing = null;
    #[OA\Property]
    public string $code;
    #[ManyToOne(class: AbstractMode::class, loadingType: LoadingType::EAGER, factoryMethod: 'loadMode'), OA\Property, NoValidate]
    public ?GameModeInterface $mode;
    #[OA\Property]
    public GameModeType $gameType = GameModeType::TEAM;
    /** @var bool Indicates if the game is synchronized to public API */
    public bool $sync = false;
    #[ManyToOne(class: MusicMode::class), OA\Property, NoValidate]
    public ?MusicModeInterface $music;
    #[ManyToOne(class: GameGroup::class), OA\Property, NoValidate]
    public ?GameGroupInterface $group;
    #[NoDB, OA\Property]
    public bool $started = false;
    #[NoDB, OA\Property]
    public bool $finished = false;
    protected float $realGameLength;

    public function __construct(?int $id = null, ?Row $dbRow = null) {
        $this->cacheTags[] = 'games/'.$this::SYSTEM;
        parent::__construct($id, $dbRow);
        $this->initExtensions();
    }

    /**
     * @return array<int, string>
     */
    public static function getTeamColors() : array {
        return [];
    }

    /**
     * @return array<int, string>
     */
    public static function getTeamNames() : array {
        return [];
    }

    /**
     * Create a new game from JSON data
     *
     * @param  array{
     *     gameType?: string,
     *     lives?: int,
     *     ammo?: int,
     *     modeName?: string,
     *     fileNumber?: int,
     *     code?: string,
     *     respawn?: int,
     *     sync?: int|bool,
     *     start?: array{date:string,timezone:string},
     *     end?: array{date:string,timezone:string},
     *     timing?: array<string,int>,
     *     scoring?: array<string,int>,
     *     mode?: array{type?:string,name:string},
     *     players?: array{
     *         id?: int,
     *         id_player?: int,
     *         name?: string,
     *         code?: string,
     *         team?: int,
     *         score?: int,
     *         skill?: int,
     *         shots?: int,
     *         accuracy?: int,
     *         vest?: int,
     *         hits?: int,
     *         deaths?: int,
     *         hitsOwn?: int,
     *         hitsOther?: int,
     *         hitPlayers?: array{target:int,count:int}[],
     *         deathsOwn?: int,
     *         deathsOther?: int,
     *         position?: int,
     *         shotPoints?: int,
     *         scoreBonus?: int,
     *         scoreMines?: int,
     *         ammoRest?: int,
     *         bonus?: array<string, int>,
     *     }[],
     *   teams?: array{
     *         id?: int,
     *         id_team?: int,
     *         name?: string,
     *         score?: int,
     *         color?: int,
     *         position?: int,
     *     }[],
     * }  $data
     *
     * @return Game
     * @throws DirectoryCreationException
     * @throws GameModeNotFoundException
     * @throws Throwable
     * @throws ValidationException
     */
    public static function fromJson(array $data) : Game {
        $game = new static();
        /** @var Player[] $players */
        $players = [];
        /** @var Team[] $teams */
        $teams = [];
        foreach ($data as $key => $value) {
            if (!property_exists($game, $key)) {
                continue;
            }
            switch ($key) {
                case 'gameType':
                    $game->gameType = GameModeType::from($value);
                    break;
                case 'modeName':
                case 'code':
                $game->{$key} = $value;
                break;
                case 'lives':
                case 'respawn':
                case 'fileNumber':
                case 'ammo':
                    assert($game instanceof Lasermaxx\Game);
                    $game->{$key} = $value;
                    break;
                case 'sync':
                    $game->{$key} = (bool) $value;
                    break;
                case 'end':
                case 'start':
                    $timezone = new DateTimeZone($value['timezone']);
                    $datetime = new DateTime($value['date']);
                    $datetime->setTimezone($timezone);
                    $game->{$key} = $datetime;
                    break;
                case 'timing':
                    $game->timing = new Timing(...$value);
                    break;
                case 'scoring':
                    assert($game instanceof Lasermaxx\Game);
                    $game->scoring = $game instanceof Evo5\Game ? new Scoring(...$value) :
                      new \Lsr\Lg\Results\LaserMaxx\Evo6\Scoring(...$value);
                    break;
                case 'mode':
                    if (!isset($value['type'])) {
                        $value['type'] = GameModeType::TEAM->value;
                    }
                    $game->mode = GameModeFactory::findByName(
                      $value['name'],
                      GameModeType::tryFrom($value['type']) ?? GameModeType::TEAM,
                      static::SYSTEM
                    );
                    break;
                case 'players':
                {
                    foreach ($value as $playerData) {
                        /** @var Player $player */
                        $player = new ($game->playerClass);
                        $player->setGame($game);
                        $id = 0;
                        foreach ($playerData as $keyPlayer => $valuePlayer) {
                            if ($keyPlayer !== 'code' && !property_exists($player, $keyPlayer)) {
                                continue;
                            }
                            switch ($keyPlayer) {
                                case 'id':
                                case 'id_player':
                                    $id = $valuePlayer;
                                    break;
                                case 'name':
                                case 'score':
                                case 'skill':
                                case 'shots':
                                case 'accuracy':
                                case 'vest':
                                case 'hits':
                                case 'deaths':
                                case 'position':
                                case 'minesHits':
                                case 'teamNum':
                                    $player->{$keyPlayer} = $valuePlayer;
                                    break;
                                case 'ammoRest':
                                case 'hitsOther':
                                case 'hitsOwn':
                                case 'deathsOther':
                                case 'deathsOwn':
                                case 'scoreBonus':
                                case 'scorePowers':
                                case 'scoreMines':
                                case 'shotPoints':
                                    assert($player instanceof \App\GameModels\Game\Lasermaxx\Player);
                                    $player->{$keyPlayer} = $valuePlayer;
                                    break;
                                case 'bonus':
                                    assert($player instanceof \App\GameModels\Game\Lasermaxx\Player);
                                    $player->bonus = new BonusCounts(...$valuePlayer);
                                    break;
                                case 'code':
                                    $player->user = \App\Models\Auth\Player::getByCode($valuePlayer);
                                    break;
                            }
                            $game->players->add($player);
                            $players[$id] = $player;
                        }
                    }
                    break;
                }
                case 'teams':
                {
                    foreach ($value as $teamData) {
                        /** @var Team $team */
                        $team = new $game->teamClass();
                        $team->setGame($game);
                        $id = 0;
                        foreach ($teamData as $keyTeam => $valueTeam) {
                            if (!property_exists($team, $keyTeam)) {
                                continue;
                            }
                            switch ($keyTeam) {
                                case 'id':
                                case 'id_team':
                                    $id = $valueTeam;
                                    break;
                                case 'name':
                                case 'score':
                                case 'color':
                                case 'position':
                                $team->{$keyTeam} = $valueTeam;
                                    break;
                            }
                            $game->addTeam($team);
                            $teams[$id] = $team;
                        }
                    }
                    break;
                }
            }
        }

        // Assign hits and teams
        foreach (($data['players'] ?? []) as $playerData) {
            $id = $playerData['id'] ?? $playerData['id_player'] ?? 0;
            if (!isset($players[$id])) {
                continue;
            }
            $player = $players[$id];
            // Hits
            foreach (($playerData['hitPlayers'] ?? []) as $hit) {
                if (isset($players[$hit['target']])) {
                    $player->addHits($players[$hit['target']], $hit['count']);
                }
            }
            // Team
            $teamId = (int) ($playerData['team'] ?? 0);
            if (isset($teams[$teamId])) {
                $player->team = $teams[$teamId];
                $teams[$teamId]->addPlayer($player);
            }
        }
        return $game;
    }

    /**
     * @return AbstractMode|null
     * @throws GameModeNotFoundException
     */
    public function getMode() : ?AbstractMode {
        if (!isset($this->mode)) {
            if (isset($this->relationIds['mode'])) {
                $this->mode = GameModeFactory::getById($this->relationIds['mode']);
            }
            else {
                if (isset($this->modeName)) {
                    $this->mode = GameModeFactory::find($this->modeName, $this->gameType, $this::SYSTEM);
                }
                else {
                    $this->mode = null;
                }
            }
        }
        return $this->mode;
    }

    public function loadMode() : ?AbstractMode {
        if (isset($this->relationIds['mode'])) {
            $mode = GameModeFactory::getById($this->relationIds['mode']);
            if ($mode !== null) {
                return $mode;
            }
        }

        if (isset($this->modeName)) {
            return GameModeFactory::find($this->modeName, $this->gameType, $this::SYSTEM);
        }
        return GameModeFactory::findModeObject($this::SYSTEM, null, $this->gameType);
    }

    public function getQueryData() : array {
        $data = parent::getQueryData();
        $this->extensionAddQueryData($data);
        return $data;
    }

    public function fillFromRow() : void {
        if (!isset($this->row)) {
            return;
        }
        parent::fillFromRow();
        $this->extensionFillFromRow();
    }

    public function isStarted() : bool {
        return $this->start !== null;
    }

    /**
     * Get best player by some property
     *
     * @param  string  $property
     *
     * @return Player|null
     * @throws ValidationException
     * @noinspection PhpMissingBreakStatementInspection
     */
    public function getBestPlayer(string $property) : ?Player {
        $query = $this->players->query()->sortBy($property);
        switch ($property) {
            case 'shots':
                $query->asc();
                break;
            case 'hitsOwn':
            case 'deathsOwn':
            /* @phpstan-ignore-next-line */
            $query->addFilter(new CollectionCompareFilter($property, Comparison::GREATER, 0));
            default:
                $query->desc();
                break;
        }
        return $query->first();
    }

    /**
     * @return array<string,string>
     * @noinspection PhpArrayShapeAttributeCanBeAddedInspection
     */
    public function getBestsFields() : array {
        $fields = [
          'hits'     => lang('Největší terminátor', context: 'bests', domain: 'results'),
          'deaths'   => lang('Objekt největšího zájmu', context: 'bests', domain: 'results'),
          'score'    => lang('Absolutní vítěz', context: 'bests', domain: 'results'),
          'accuracy' => lang('Hráč s nejlepší muškou', context: 'bests', domain: 'results'),
          'shots'    => lang('Nejúspornější střelec', context: 'bests', domain: 'results'),
          'miss'     => lang('Největší mimoň', context: 'bests', domain: 'results'),
        ];
        foreach ($fields as $key => $value) {
            $settingName = Strings::toCamelCase('best_'.$key);
            if (!($this->mode->settings->$settingName ?? true)) {
                unset($fields[$key]);
            }
        }
        return $fields;
    }

    /**
     * Get player by vest number
     *
     * @param  int|string  $vestNum
     *
     * @return Player|null
     */
    public function getVestPlayer(int | string $vestNum) : ?Player {
        return $this->players->query()->filter('vest', $vestNum)->first();
    }

    /**
     * @return array<string, mixed>
     * @throws DirectoryCreationException
     * @throws GameModeNotFoundException
     * @throws ValidationException
     */
    public function jsonSerialize() : array {
        $data = parent::jsonSerialize();
        $data['system'] = $this::SYSTEM;
        $data['group'] = null;
        if ($this->group !== null) {
            $data['group'] = [
              'id'     => $this->group->id,
              'name'   => $this->group->name,
              'active' => $this->group->active,
            ];
        }
        $data['metaData'] = $this->getMeta();
        $data['mode'] = $this->mode?->jsonSerialize();
        if (isset($data['mode'])) {
            $data['mode']['variations'] = $this->getMeta()['variations'] ?? [];
        }
        $this->extensionJson($data);
        return $data;
    }

    public function getMusic() : ?MusicMode {
        $this->music ??= isset($this->relationIds['music']) ? MusicMode::get($this->relationIds['music']) : null;
        return $this->music;
    }

    /**
     * Synchronize a game to public
     *
     * @return bool
     * @throws DirectoryCreationException
     * @throws JsonException
     * @throws Throwable
     */
    public function sync() : bool {
        /** @var FeatureConfig $featureConfig */
        $featureConfig = App::getService('features');
        if (!$featureConfig->isFeatureEnabled('liga')) {
            return false;
        }

        /** @var LigaApi $liga */
        $liga = App::getService('liga');
        if ($liga->syncGames($this::SYSTEM, [$this])) {
            $this->sync = true;
            try {
                return $this->save();
            } catch (ValidationException) {
            }
        }
        return false;
    }

    /**
     * @return bool
     * @throws DirectoryCreationException
     * @throws ValidationException
     * @throws Throwable
     * @noinspection PhpUndefinedFieldInspection
     */
    public function save() : bool {
        $pk = $this::getPrimaryKey();
        /** @var Row|null $test */
        $test = DB::select($this::TABLE, $pk.', code')->where(
          'start = %dt OR start = %dt',
          $this->start,
          $this->start->getTimestamp() + ($this->timing->before ?? 20)
        )->fetch(cache: false);
        if (isset($test)) {
            $this->id = $test->$pk;
            $this->code = $test->code;
        }
        if (empty($this->code)) {
            $this->code = uniqid(self::getCodePrefix(), false);
        }
        $success = parent::save();
        if (!$success) {
            return false;
        }

        $this->calculateSkills();

        foreach ($this->teams as $team) {
            $success &= $team->save();
        }
        if (!$success) {
            return false;
        }
        if ($this->teams->count() === 0) {
            /** @var Player $player */
            foreach ($this->players as $player) {
                $success = $success && $player->save();
            }
        }

        if ($this->getGroup() !== null) {
            $success = $success && $this->getGroup()->save();
        }

        return $success && $this->extensionSave();
    }

    public static function getCodePrefix() : string {
        if (!isset(self::$codePrefix)) {
            $config = App::getService('config');
            assert($config instanceof Config);
            $prefix = $config->getConfig('ENV')['GAME_PREFIX'] ?? 'g';
            self::$codePrefix = is_string($prefix) ? $prefix : 'g';
        }
        return self::$codePrefix;
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function calculateSkills() : void {
        /** @var Player[] $players */
        $players = $this->players->getAll();

        // Calculate the base skill for all players first
        $skills = [];
        foreach ($players as $player) {
            $skills[] = $player->calculateSkill();
        }
        // -1 because we will always subtract one player while calculating the average
        $playerCount = count($skills) - 1;
        if ($playerCount === 0) {
            return;
        }
        $skillSum = array_sum($skills);

        // Modulate the skill value based on the average skill value for each player.
        // This should lower the skill value for players, if they are playing against weak opponents and vice versa.
        foreach ($players as $player) {
            // Recalculate the average skill of all other players using the skill sum
            $avg = ($skillSum - $player->skill) / $playerCount;
            // Negative if the player skill is greater than the average and vice versa
            $diff = $avg - $player->skill;
            if ($avg === 0) {
                $avg = 1;
            }
            $diffPercent = abs($diff / $avg);

            // 1-(1/x) has an asymptote in y=1, therefore it is never possible to lower the skill value by 100%.
            $percent = 1 - (8 / ($diffPercent + 8));
            $newDiff = (int) abs(round($player->skill * $percent));
            if ($diff < 0) {
                $player->skill -= $newDiff;
            }
            else {
                $player->skill += $newDiff;
            }
        }
    }

    public function getGroup() : ?GameGroup {
        $this->group ??= isset($this->relationIds['group']) ? GameGroup::get($this->relationIds['group']) : null;
        return $this->group;
    }

    public function insert() : bool {
        if ($this->getGroup() !== null) {
            $this->getGroup()->clearCache();
        }
        /** @var Cache $cache */
        $cache = App::getService('cache');
        $cache->clean([CacheParent::Tags => ['games/counts']]);
        return parent::insert();
    }

    public function clearCache() : void {
        parent::clearCache();

        // Invalidate cached objects
        /** @var Cache $cache */
        $cache = App::getService('cache');
        $cache->remove('games/'.$this::SYSTEM.'/'.$this->id);
        $cache->clean(
          [
            CacheParent::Tags => [
              'games/'.$this::SYSTEM.'/'.$this->id,
              'games/'.$this->start?->format('Y-m-d'),
              'games/'.$this->start?->format('Y-m'),
              'games/'.$this->start?->format('Y'),
              'games/'.$this->code,
            ],
          ]
        );

        if ($this->getGroup() !== null) {
            $this->getGroup()->clearCache();
        }

        // Invalidate generated results cache
        $files = glob(TMP_DIR.'results/'.$this->code.'*');
        if ($files !== false) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
    }

    public function delete() : bool {
        /** @var Cache $cache */
        $cache = App::getService('cache');
        $cache->clean([CacheParent::Tags => ['games/counts']]);
        return parent::delete();
    }

    /**
     * Get the real game length in minutes.
     *
     * @return float Real game length in minutes.
     */
    public function getRealGameLength() : float {
        if (!isset($this->realGameLength)) {
            if (!isset($this->end, $this->start) || !$this->isFinished()) {
                // If the game is not finished, it does not have a game length
                return 0;
            }
            $diff = $this->start->diff($this->end);
            $this->realGameLength = (($diff->h * 3600) + ($diff->i * 60) + $diff->s) / 60;
        }
        return $this->realGameLength;
    }

    public function isFinished() : bool {
        return $this->end !== null && $this->importTime !== null;
    }

    /**
     * @return float
     */
    public function getAverageKd() : float {
        try {
            /** @var float[] $kds */
            $kds = $this->players->query()->map(fn(Player $player) => $player->getKd())->get();
        } catch (ValidationException | DirectoryCreationException) {
            return 1;
        }
        return empty($kds) ? 1 : array_sum($kds) / count($kds);
    }

    public function recalculateScores() : void {
        if ($this->mode !== null) {
            $this->mode->recalculateScores($this);
            $this->reorder();
            $this->sync = false;
        }
    }

    public function reorder() : void {
        $this->mode?->reorderGame($this);
        $this->runHook('reorder');
    }
}

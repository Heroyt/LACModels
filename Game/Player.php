<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game;

use App\Exceptions\InsufficientRegressionDataException;
use App\GameModels\Factory\PlayerFactory;
use App\GameModels\Traits\Expandable;
use App\GameModels\Traits\WithGame;
use App\Models\Auth\Player as User;
use App\Models\BaseModel;
use Dibi\Exception;
use Dibi\Row;
use Lsr\Db\DB;
use Lsr\Helpers\Tools\Strings;
use Lsr\LaserLiga\PlayerInterface as UserInterface;
use Lsr\Lg\Results\Interface\Models\PlayerInterface;
use Lsr\Lg\Results\Interface\Models\TeamInterface;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\ObjectValidation\Attributes\Required;
use Lsr\ObjectValidation\Attributes\StringLength;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\NoDB;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Throwable;

/**
 * Base class for player models
 *
 * @property \LAC\Modules\Tournament\Models\Player|null $tournamentPlayer
 *
 * @template G of Game
 * @template T of Team
 *
 * @use WithGame<G>
 * @use PlayerCalculatedProperties<G,T>
 */
#[PrimaryKey('id_player')]
#[Factory(PlayerFactory::class)] // @phpstan-ignore-line
abstract class Player extends BaseModel implements PlayerInterface
{
    /** @phpstan-use WithGame<G> */
    use WithGame;
    use Expandable;
    use PlayerCalculatedProperties;

    /** @var string[] */
    public const array CACHE_TAGS = ['players'];
    /** @var string[] */
    public const array CLASSIC_BESTS = ['score', 'hits', 'score', 'accuracy', 'shots', 'miss'];
    public const string SYSTEM = '';
    public const string DI_TAG = 'playerDataExtension';

    #[Required]
    #[StringLength(min: 1, max: 50)]
    public string $name = '';
    public int $score = 0;
    public int $skill = 0;
    public int | string $vest = 0;
    public int $shots = 0;
    public int $accuracy = 0;
    public int $hits = 0;
    public int $deaths = 0;
    public int $position = 0;

    /** @var PlayerHit[] */
    #[NoDB]
    public ?array $hitPlayers = [];

    /** @var T|null */
    #[ManyToOne(class: Team::class, foreignKey: 'id_team')]
    public ?TeamInterface $team = null;

    #[ManyToOne(class: User::class)]
    public ?UserInterface $user = null;

    public function __construct(?int $id = null, ?Row $dbRow = null) {
        $this->cacheTags[] = 'games/'.$this::SYSTEM;
        $this->cacheTags[] = 'players/'.$this::SYSTEM;
        parent::__construct($id, $dbRow);
        $this->initExtensions();
        $this->hitPlayers ??= [];
    }

    /**
     * @return bool
     * @throws ValidationException
     */
    public function save() : bool {
        try {
            /** @var int|null $test */
            $test = DB::select($this::TABLE, $this::getPrimaryKey())
                      ->where(
                        'id_game = %i && name = %s && vest = '.(is_string($this->vest) ? '%s' : '%i'),
                        $this->game->id,
                        $this->name,
                        $this->vest
                      )
                      ->fetchSingle(cache: false);
            if (isset($test)) {
                $this->id = $test;
            }
        } catch (Throwable) {
        }

        //$this->calculateSkill();
        return parent::save() && $this->extensionSave();
    }

    /**
     * Get the expected number of deaths based on enemy and teammate count for this player.
     *
     * We used regression to calculate the best model to describe the best model to predict the average number of hits
     * based on the player's enemy and teammate count. We can easily calculate the expected average deaths count for
     * each player based on our findings.
     *
     * @return float
     * @throws Throwable
     */
    public function getExpectedAverageDeathCount() : float {
        $enemyPlayerCount = $this->game->playerCount - ($this->game->mode?->isSolo() ? 1 : $this->team?->playerCount);
        $teamPlayerCount = ($this->team->playerCount ?? 1) - 1;
        if ($this->game->mode?->isTeam()) {
            return (2.730673 * $enemyPlayerCount) + (-0.0566788 * $teamPlayerCount) + 43.203734389;
        }
        return (4.01628957539 * $enemyPlayerCount) - 15.0000175286;
    }

    /**
     * Calculate a skill level based on the player's results
     *
     * The skill value aims to better evaluate the player's play style than the regular score value.
     * It should take multiple metrics into account.
     * Other LG system implementations should modify this function to calculate the value based on its specific metrics.
     * The value must be normalized based on the game's length.
     *
     * @pre  The player's results should be set.
     * @post The Player::$skill property is set to the calculated value
     *
     * @return int A whole number evaluation on an arbitrary scale (no max or min value).
     * @throws Throwable
     */
    public function calculateSkill() : int {
        $this->skill = (int) round($this->calculateBaseSkill());

        return $this->skill;
    }

    /**
     * Calculate the base, not rounded skill level
     *
     * @return float
     * @throws Throwable
     */
    protected function calculateBaseSkill() : float {
        $skill = 0.0;

        // Add points for hits - average hits <=> 300 points
        try {
            $skill += $this->calculateSkillForHits();
        } catch (InsufficientRegressionDataException) {
            // Ignore error
        }

        // Add points for K:D - average K:D <=> 130 points
        $skill += $this->calculateSkillFromKD();

        // Add points for accuracy - 100% accuracy <=> 500 points
        $skill += $this->calculateSkillFromAccuracy();

        // Add points for position - 100th percentile <=> 200 points
        $skill += $this->calculateSkillFromPosition();

        return $skill;
    }

    /**
     * @return float
     * @throws Throwable
     */
    protected function calculateSkillForHits() : float {
        $expectedAverageHits = $this->getExpectedAverageHitCount();
        $hitsDiff = $this->hits - $expectedAverageHits;

        // Normalize to value between <0,...) where the value of 1 corresponds to exactly average hit count
        $hitsDiffPercent = 1 + ($hitsDiff / $expectedAverageHits);

        // Completely average game should acquire at least 300 points
        return $hitsDiffPercent * 200;
    }

    /**
     * Get the expected number of hits based on enemy and teammate count for this player.
     *
     * Based on data collected, players hits on average 12.5 enemies per enemy with 6.15 standard deviation.
     * We used regression to calculate the best model to describe the best model to predict the average number of hits
     * based on the player's enemy and teammate count. We can easily calculate the expected average hit count for each
     * player based on our findings.
     *
     * @return float
     * @throws Throwable
     */
    public function getExpectedAverageHitCount() : float {
        $enemyPlayerCount = $this->game->playerCount - ($this->game->mode?->isSolo() ? 1 : $this->team?->playerCount);
        $teamPlayerCount = ($this->team->playerCount ?? 1) - 1;
        if ($this->game->mode?->isTeam()) {
            return (2.5771 * $enemyPlayerCount) + (2.48007 * $teamPlayerCount) + 36.76356;
        }
        return (2.05869 * $enemyPlayerCount) + 44.8715;
    }

    /**
     * @return float
     * @throws Throwable
     */
    protected function calculateSkillFromKD() : float {
        $kd = $this->getKd();
        $skill = 0.0;
        if ($kd >= 1) {
            $skill += $kd * 50;
        }
        else {
            if ($kd !== 0.0) {
                $skill -= (1 / $kd) * 5;
            }
        }

        // Add points for deviation from an average K:D
        $averageKd = $this->game->getAverageKd();
        if ($averageKd === 0.0) {
            $averageKd = 1.0;
        }
        $kdDiff = 1 + (($kd - $averageKd) / $averageKd); // $average K:D should never be 0 if any hits were fired
        $skill += $kdDiff * 80;

        return $skill;
    }

    public function getKd() : float {
        return $this->hits / ($this->deaths === 0 ? 1 : $this->deaths);
    }

    /**
     * @return float
     */
    protected function calculateSkillFromAccuracy() : float {
        return 500 * ($this->accuracy / 100);
    }

    protected function calculateSkillFromPosition() : float {
        $pos = 0;
        $realPos = 0;
        $prevScore = null;
        /** @var Player $player */
        foreach ($this->game->playersSorted as $player) {
            if ($player->vest === $this->vest) {
                break;
            }
            $realPos++;
            if ($prevScore !== $player->score) {
                $prevScore = $player->score;
                $pos = $realPos;
            }
        }

        $playerCount = $this->game->playerCount;
        return 200.0 * ($playerCount - $pos) / $playerCount;
    }

    /**
     * @return bool
     */
    public function saveHits() : bool {
        if (empty($this->hitPlayers)) {
            return true;
        }
        $table = '';
        $values = [];
        foreach ($this->hitPlayers as $hits) {
            $table = $hits::TABLE;
            $values[] = $hits->getQueryData();
        }
        try {
            return DB::replace($table, $values) > 0;
        } catch (Exception $e) {
            $this->logger->debug(json_encode($this->hitPlayers));
            $this->logger->debug(json_encode($values));
            $this->getLogger()->exception($e);
            return false;
        }
    }

    public function getQueryData() : array {
        $data = parent::getQueryData();
        $this->extensionAddQueryData($data);
        return $data;
    }

    /**
     * Get a players position in today's leaderboard
     *
     * @param  string  $property
     *
     * @return int
     */
    public function getTodayPosition(string $property) : int {
        return 0; // TODO: Implement
    }

    /**
     * Get one trophy
     *
     * @return array{name:string,icon:string}
     * @throws ValidationException
     */
    public function getBestAt() : array {
        if (!isset($this->trophy)) {
            $this->trophy = new PlayerTrophy($this);
        }
        return $this->trophy->getOne();
    }

    /**
     * Get all trophies
     *
     * @return array{name:string,icon:string}[]
     * @throws ValidationException
     */
    public function getAllBestAt() : array {
        if (!isset($this->trophy)) {
            $this->trophy = new PlayerTrophy($this);
        }
        return $this->trophy->getAll();
    }

    /**
     * @param  Player<G,T>  $player
     *
     * @return int
     * @throws DirectoryCreationException
     * @throws ValidationException
     */
    public function getHitsPlayer(PlayerInterface $player) : int {
        return $this->getHitsPlayers()[$player->vest]->count ?? 0;
    }

    /**
     * @return PlayerHit[]
     * @throws DirectoryCreationException
     * @throws ValidationException
     */
    public function getHitsPlayers() : array {
        if (empty($this->hitPlayers)) {
            return $this->loadHits();
        }
        return $this->hitPlayers;
    }

    /**
     * @return PlayerHit[]
     * @throws ValidationException
     * @throws DirectoryCreationException
     */
    public function loadHits() : array {
        /** @var PlayerHit $className */
        $className = str_replace('Player', 'PlayerHit', get_class($this));
        $hits = DB::select($className::TABLE, 'id_target, count')->where('id_player = %i', $this->id)->fetchAll();
        foreach ($hits as $row) {
            $this->addHits($this::get($row->id_target), $row->count);
        }
        return $this->hitPlayers;
    }

    /**
     * @param  Player<G,T>  $player
     * @param  int  $count
     *
     * @return $this
     */
    public function addHits(PlayerInterface $player, int $count = 1) : static {
        /** @var class-string<PlayerHit> $className */
        $className = str_replace('Player', 'PlayerHit', $this::class);
        if (isset($this->hitPlayers[$player->vest])) {
            $this->hitPlayers[$player->vest]->count += $count;
            return $this;
        }
        $this->hitPlayers[$player->vest] = new $className($this, $player, $count);
        return $this;
    }

    /**
     * @return array<string, mixed>
     * @throws ValidationException
     * @throws DirectoryCreationException
     */
    public function jsonSerialize() : array {
        $data = parent::jsonSerialize();
        $data['user'] = $this->user?->id;
        if (isset($this->user)) {
            $data['code'] = $this->user->getCode();
            $data['rank'] = $this->user->rank;
        }
        $data['hitPlayers'] = $this->getHitsPlayers();
        $data['avgSkill'] = $this->getSkill();
        $this->extensionJson($data);
        return $data;
    }

    /**
     * Get the player's skill value. If the player is a part of a group, returns the average group value.
     *
     * @return int
     */
    public function getSkill() : int {
        if ($this->game->getGroup() === null) {
            return $this->skill;
        }
        try {
            $players = $this->game->getGroup()->players;
            $name = Strings::toAscii($this->name);
            if (isset($players[$name])) {
                return $players[$name]->getSkill();
            }
        } catch (Throwable) {
        }
        return $this->skill;
    }

    /**
     * @return array<string,float>
     * @throws Throwable
     */
    public function getSkillParts() : array {
        $hits = 0.0;
        try {
            $hits = $this->calculateSkillForHits();
        } catch (InsufficientRegressionDataException) {
            // Ignore
        }
        return [
          'position' => $this->calculateSkillFromPosition(),
          'hits'     => $hits,
          'kd'       => $this->calculateSkillFromKD(),
          'accuracy' => $this->calculateSkillFromAccuracy(),
        ];
    }

    public function getRankDifference() : ?float {
        if (!isset($this->user)) {
            return null;
        }
        return DB::select('player_game_rating', '[difference]')->where(
          '[id_user] = %i AND [code] = %s',
          $this->user->id,
          $this->game->code
        )->fetchSingle(false);
    }

    public function fillFromRow() : void {
        if (!isset($this->row)) {
            return;
        }
        parent::fillFromRow();
        $this->extensionFillFromRow();
    }

}

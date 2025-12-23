<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game;

use App\GameModels\Factory\TeamFactory;
use App\GameModels\Traits\Expandable;
use App\GameModels\Traits\WithGame;
use App\GameModels\Traits\WithPlayers;
use App\Models\BaseModel;
use Dibi\Row;
use Lsr\Db\DB;
use Lsr\Lg\Results\Interface\Models\TeamInterface;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\ObjectValidation\Attributes\Required;
use Lsr\ObjectValidation\Attributes\StringLength;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Transforms\Truncate;
use Throwable;

/**
 * Base class for Team models
 *
 * @property \LAC\Modules\Tournament\Models\Team|null $tournamentTeam
 *
 * @template P of Player
 * @template G of Game
 *
 * @use WithPlayers<P>
 * @use WithGame<G>
 * @implements TeamInterface<P, G>
 */
#[PrimaryKey('id_team')]
#[Factory(TeamFactory::class)] // @phpstan-ignore-line
abstract class Team extends BaseModel implements TeamInterface
{
    /** @phpstan-use WithPlayers<P> */
    use WithPlayers;

    /** @phpstan-use WithGame<G> */
    use WithGame;
    use Expandable;

    public const string SYSTEM = '';

    public const string DI_TAG = 'teamDataExtension';

    #[Required]
    public int $color = 0;
    #[Required]
    public int $score = 0;
    public ?int $bonus = null;
    #[Required]
    public int $position = 0;
    #[Required]
    #[StringLength(min: 1, max: 99), Truncate(99)]
    public string $name = '';


    public function __construct(?int $id = null, ?Row $dbRow = null) {
        $this->cacheTags[] = 'games/'.$this::SYSTEM;
        $this->cacheTags[] = 'teams/'.$this::SYSTEM;
        parent::__construct($id, $dbRow);
        $this->playerCount = $this->players->count();
        $this->initExtensions();
    }

    public function save() : bool {
        try {
            /** @var int|null $test */
            $test = DB::select($this::TABLE, $this::getPrimaryKey())->where(
              'id_game = %i && name = %s',
              $this->game->id,
              $this->name
            )->fetchSingle(cache: false);
            if (isset($test)) {
                $this->id = $test;
            }
        } catch (Throwable) {
        }

        return parent::save() && $this->extensionSave();
    }

    /**
     * @return int
     */
    public function getDeaths() : int {
        $sum = 0;
        foreach ($this->players as $player) {
            $sum += $player->deaths;
        }
        return $sum;
    }

    /**
     * @return float
     */
    public function getAccuracy() : float {
        return $this->getShots() === 0 ? 0 : round(100 * $this->getHits() / $this->getShots(), 2);
    }

    /**
     * @return int
     */
    public function getShots() : int {
        $sum = 0;
        foreach ($this->players as $player) {
            $sum += $player->shots;
        }
        return $sum;
    }

    /**
     * @return int
     */
    public function getHits() : int {
        $sum = 0;
        foreach ($this->players as $player) {
            $sum += $player->hits;
        }
        return $sum;
    }

    /**
     * @param  static  $team
     *
     * @return int
     * @throws ValidationException
     * @throws DirectoryCreationException
     */
    public function getHitsTeam(Team $team) : int {
        $sum = 0;
        foreach ($this->players as $player) {
            foreach ($player->getHitsPlayers() as $hits) {
                if ($hits->playerTarget->team?->color === $team->color) {
                    $sum += $hits->count;
                }
            }
        }
        return $sum;
    }

    /**
     * @param  bool  $includeSystem
     *
     * @return string
     * @throws Throwable
     */
    public function getTeamBgClass(bool $includeSystem = false) : string {
        return 'team-'.($includeSystem ? ($this->game::SYSTEM).'-' : '').$this->color;
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
        if (isset($data['data'])) {
            unset($data['data']);
        }
        if (isset($data['hooks'])) {
            unset($data['hooks']);
        }
        if (isset($data['players'])) {
            unset($data['players']);
        }
        $this->extensionJson($data);
        return $data;
    }

    /**
     * @param  int  $bonus
     *
     * @return static
     */
    public function setBonus(int $bonus) : static {
        $this->bonus = $bonus;
        $this->runHook('setBonus', $bonus);
        return $this;
    }

    /**
     * @return int
     */
    public function getScore() : int {
        $score = $this->score;
        if (isset($this->bonus)) {
            $score += $this->bonus;
        }
        return $score;
    }

    public function fillFromRow() : void {
        if (!isset($this->row)) {
            return;
        }
        parent::fillFromRow();
        $this->extensionFillFromRow();
    }

    public function getQueryData(bool $filterChanged = true) : array {
        $data = parent::getQueryData($filterChanged);
        $this->extensionAddQueryData($data);
        return $data;
    }
}

<?php

namespace App\GameModels\Game\LaserForce;

use App\GameModels\Game\LaserForce\DataObjects\LaserBallCounts;
use App\GameModels\Game\LaserForce\DataObjects\PowerCount;
use App\GameModels\Game\LaserForce\Enums\PlayerRole;
use App\GameModels\Game\LaserForce\Enums\TargetHitType;
use Dibi\Exception;
use Lsr\Db\DB;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\Orm\Attributes\Instantiate;
use Lsr\Orm\Attributes\NoDB;
use Lsr\Orm\Exceptions\ModelNotFoundException;

/**
 * @extends \App\GameModels\Game\Player<Game, Team>
 */
class Player extends \App\GameModels\Game\Player
{
    public const string TABLE = 'laserforce_players';
    public const SYSTEM = 'laserForce';

    public string $identifier = '';
    public int $level = 0;
    public int $category = 0;

    public int $hitsOther = 0;
    public int $hitsOwn = 0;
    public int $deathsOwn = 0;
    public int $deathsOther = 0;

    public int $rocketTargetCount = 0;
    public int $rocketMissCount = 0;
    public int $rocketCount = 0;
    public int $rocketDeathCount = 0;

    public int $punishCount = 0;

    public int $addedLives = 0;
    public int $addedAmmo = 0;
    public int $addedTeamLives = 0;
    public int $addedTeamAmmo = 0;
    public int $livesAddedTo = 0;
    public int $ammoAddedTo = 0;

    public int $beaconCount = 0;

    public PlayerRole $role = PlayerRole::PLAYER;

    /** @var Event[] */
    #[NoDB]
    public array $events = [];

    public int $targetHitsCount = 0;
    public int $targetsDestroyedCount = 0;

    /** @var array{hits:TargetHit[],destroyed:TargetHit[]} */
    #[NoDB]
    public array $targetHits = [
      'hits'      => [],
      'destroyed' => [],
    ];

    #[Instantiate]
    public PowerCount $powers;
    #[Instantiate]
    public LaserBallCounts $laserBall;

    /**
     * @param  Event  $event
     *
     * @return $this
     */
    public function addEvent(Event $event) : static {
        if (!isset($this->events[$event->time])) {
            $this->events[$event->time] = $event;
        }
        else {
            for ($i = 0; $i < 5; $i++) {
                $key = $event->time.'-'.$i;
                if (!isset($this->events[$key])) {
                    $this->events[$key] = $event;
                    return $this;
                }
            }
        }
        return $this;
    }

    public function saveHits() : bool {
        // Save player hits
        $success = parent::saveHits();

        // Save target hits
        $table = '';
        $values = [];
        foreach ($this->targetHits as $hits) {
            foreach ($hits as $hit) {
                $table = $hit::TABLE;
                $values[] = $hit->getQueryData();
            }
        }
        try {
            /** @phpstan-ignore-next-line */
            $success = $success && DB::replace($table, $values) > 0;
        } catch (Exception) {
            return false;
        }

        return $success;
    }

    /**
     * @return array{hits:TargetHit[],destroyed:TargetHit[]}
     * @throws DirectoryCreationException
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function getTargetHits() : array {
        if (empty($this->targetHits['hits']) && empty($this->targetHits['destroyed'])) {
            $this->loadTargetHits();
        }
        return $this->targetHits;
    }

    /**
     * @return array{hits:TargetHit[],destroyed:TargetHit[]}
     * @throws ModelNotFoundException
     * @throws ValidationException
     * @throws DirectoryCreationException
     */
    public function loadTargetHits() : array {
        $hits = DB::select(TargetHit::TABLE, 'id_target, count, type')->where('id_player = %i', $this->id)->fetchAll();
        foreach ($hits as $row) {
            if ($row->type === TargetHitType::HIT->value) {
                $this->addTargetHits(Target::get($row->id_target), $row->count);
            }
            else {
                $this->addTargetDestroyed(Target::get($row->id_target), $row->count);
            }
        }
        return $this->targetHits;
    }

    /**
     * @param  Target  $target
     * @param  int  $count
     *
     * @return $this
     */
    public function addTargetHits(Target $target, int $count = 1) : static {
        if (isset($this->targetHits['hits'][$target->identifier])) {
            $this->targetHits['hits'][$target->identifier]->count += $count;
            return $this;
        }
        $this->targetHits['hits'][$target->identifier] = new TargetHit($this, $target, $count);
        return $this;
    }

    /**
     * @param  Target  $target
     * @param  int  $count
     *
     * @return $this
     */
    public function addTargetDestroyed(Target $target, int $count = 1) : static {
        if (isset($this->targetHits['destroyed'][$target->identifier])) {
            $this->targetHits['destroyed'][$target->identifier]->count += $count;
            return $this;
        }
        $this->targetHits['destroyed'][$target->identifier] = new TargetHit(
          $this,
          $target,
          $count,
          TargetHitType::DESTROYED
        );
        return $this;
    }
}

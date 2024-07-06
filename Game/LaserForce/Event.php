<?php

namespace App\GameModels\Game\LaserForce;

use App\Exceptions\ResultsParseException;
use App\GameModels\Game\LaserForce\Enums\EventType;
use App\GameModels\Game\LaserForce\Interfaces\CustomEventsInterface;
use App\GameModels\Traits\WithGame;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;

/**
 * @use WithGame<Game>
 */
#[PrimaryKey('id_event')]
class Event extends Model
{
    /** @phpstan-use WithGame<Game> */
    use WithGame;

    public const TABLE = 'laserforce_events';

    /** @var positive-int */
    public int $time;
    public ?int $score1 = null;
    public ?int $score2 = null;
    public string $typeId = '';
    public ?EventType $type   = null;
    public Player $actor1;
    public null|Player|Target $actor2;

    /**
     * Process anything related to the event
     *
     * This method should update all necessary values on all the actors and game if necessary.
     *
     * @warning Should not add any score. The score is added using a different record.
     *
     * @return void
     * @throws ResultsParseException
     */
    public function process(): void {
        switch ($this->type) {
            case EventType::HIT:
                if (!isset($this->actor2)) {
                    throw new ResultsParseException('Hit event must have both actors - event time: ' . $this->time);
                }
                if ($this->actor2 instanceof Player) {
                    $this->actor1->shots++;
                    $this->actor1->hits++;
                    $this->actor1->hitsOther++;
                    $this->actor2->deaths++;
                    $this->actor2->deathsOther++;
                    $this->actor1->addHits($this->actor2);
                } else {
                    $this->actor1->targetHitsCount++;
                    $this->actor1->addTargetHits($this->actor2);
                }
                break;
            case EventType::HIT_OWN:
                if (!isset($this->actor2)) {
                    throw new ResultsParseException('Hit event must have both actors - event time: ' . $this->time);
                }
                if ($this->actor2 instanceof Player) {
                    $this->actor1->shots++;
                    $this->actor1->hits++;
                    $this->actor1->hitsOwn++;
                    $this->actor2->deaths++;
                    $this->actor2->deathsOwn++;
                    $this->actor1->addHits($this->actor2);
                }
                break;
            case EventType::TARGET_HIT:
                if (!isset($this->actor2)) {
                    throw new ResultsParseException('Hit event must have both actors - event time: ' . $this->time);
                }
                if ($this->actor2 instanceof Target) {
                    $this->actor1->shots++;
                    $this->actor1->targetHitsCount++;
                    $this->actor1->addTargetHits($this->actor2);
                }
                break;
            case EventType::TARGET_ROCKET_DESTROYED:
            case EventType::TARGET_DESTROYED:
                if (!isset($this->actor2)) {
                    throw new ResultsParseException('Hit event must have both actors - event time: ' . $this->time);
                }
                if ($this->actor2 instanceof Target) {
                    $this->actor1->shots++;
                    $this->actor1->targetsDestroyedCount++;
                    $this->actor1->addTargetDestroyed($this->actor2);
                }
                break;
            case EventType::TARGETS:
                $this->actor1->rocketTargetCount++;
                break;
            case EventType::TARGET_MISS:
            case EventType::ROCKET_MISS:
                $this->actor1->rocketMissCount++;
                break;
            case EventType::ROCKET:
                if (!isset($this->actor2)) {
                    throw new ResultsParseException('Rocket event must have both actors - event time: ' . $this->time);
                }
                $this->actor1->rocketCount++;
                if ($this->actor2 instanceof Player) {
                    $this->actor1->hits++;
                    $this->actor1->hitsOther++;
                    $this->actor1->rocketCount++;
                    $this->actor2->deaths++;
                    $this->actor2->deathsOther++;
                    $this->actor2->rocketDeathCount++;
                    $this->actor1->addHits($this->actor2);
                } else {
                    $this->actor1->targetHitsCount++;
                    $this->actor1->addTargetHits($this->actor2);
                }
                break;
            case EventType::MISS:
                $this->actor1->shots++;
                break;
            case EventType::LEVEL_UP:
                $this->actor1->level++;
                break;
            case EventType::PUNISHED:
                $this->actor1->punishCount++;
                break;
            case EventType::MACHINE_GUN:
                $this->actor1->powers->machineGun++;
                break;
            case EventType::INVINCIBILITY:
                $this->actor1->powers->invincibility++;
                break;
            case EventType::NUKE_START:
                $this->actor1->powers->nukeStart++;
                break;
            case EventType::NUKE:
                $this->actor1->powers->nuke++;
                break;
            case EventType::PAYBACK:
                $this->actor1->powers->payback++;
                break;
            case EventType::RESET:
                $this->actor1->powers->reset++;
                break;
            case EventType::SHIELD:
                $this->actor1->powers->shield++;
                break;
            case EventType::ADD_LIVES:
                if (!isset($this->actor2) || !($this->actor2 instanceof Player)) {
                    throw new ResultsParseException('Add lives event must have both actors - event time: ' . $this->time);
                }
                $this->actor1->addedLives++;
                $this->actor2->livesAddedTo++;
                break;
            case EventType::ADD_TEAM_LIVES:
                $this->actor1->addedTeamLives++;
                break;
            case EventType::ADD_AMMO:
                if (!isset($this->actor2) || !($this->actor2 instanceof Player)) {
                    throw new ResultsParseException('Add ammo event must have both actors - event time: ' . $this->time);
                }
                $this->actor1->addedAmmo++;
                $this->actor2->ammoAddedTo++;
                break;
            case EventType::ADD_TEAM_AMMO:
                $this->actor1->addedTeamAmmo++;
                break;
            case EventType::MODE_ACTION_1:
            case EventType::MODE_ACTION_2:
            case EventType::MODE_ACTION_3:
            case EventType::MODE_ACTION_4:
            case EventType::MODE_ACTION_5:
            case EventType::MODE_ACTION_6:
            case EventType::MODE_ACTION_7:
            case EventType::MODE_ACTION_8:
            case EventType::MODE_ACTION_9:
            case EventType::MODE_ACTION_10:
                if ($this->getGame()->getMode() instanceof CustomEventsInterface) {
                    $this->getGame()->getMode()->processEvent($this);
                }
                break;
            case EventType::BEACON:
                $this->actor1->beaconCount++;
                break;
            default:
                break; // Do nothing
        }
    }
}

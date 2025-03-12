<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game\LaserForce\Traits;

use App\GameModels\Game\Game;
use App\GameModels\Game\LaserForce\Collections\TargetCollection;
use App\GameModels\Game\LaserForce\Target;
use App\GameModels\Game\Team;
use InvalidArgumentException;
use Lsr\Db\DB;
use Lsr\Helpers\Tools\Timer;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\Orm\Attributes\Instantiate;
use Lsr\Orm\Attributes\NoDB;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Throwable;

trait WithTargets
{
    /** @var int */
    #[NoDB]
    public int $targetCount = 0;

    #[Instantiate]
    public TargetCollection $targets;

    public function addTarget(Target ...$targets) : static {
        if (!isset($this->targets)) {
            $this->targets = new TargetCollection();
        }
        $this->targets->add(...$targets);
        if ($this instanceof Team) {
            foreach ($targets as $target) {
                $target->setTeam($this);
            }
        }
        return $this;
    }

    /**
     * @return bool
     * @throws ValidationException
     */
    public function saveTargets() : bool {
        if (!isset($this->targets)) {
            return true;
        }
        Timer::start('game.save.targets');
        /** @var Target $target */
        // Save targets first
        foreach ($this->targets as $target) {
            if (!$target->save()) {
                Timer::stop('game.save.targets');
                return false;
            }
        }
        Timer::stop('game.save.targets');
        return true;
    }

    /**
     * @return int
     */
    public function getTargetCount() : int {
        if (!isset($this->targetCount) || $this->targetCount < 1) {
            $this->targetCount = $this->getTargets()->count();
        }
        return $this->targetCount;
    }

    /**
     * @return TargetCollection
     */
    public function getTargets() : TargetCollection {
        if (!isset($this->targets)) {
            $this->targets = new TargetCollection();
        }
        if (!empty($this->id) && $this->targets->count() === 0) {
            try {
                $this->loadTargets();
            } catch (Throwable $e) {
                // Do nothing
            }
        }
        return $this->targets;
    }

    /**
     * @return TargetCollection
     * @throws DirectoryCreationException
     * @throws ModelNotFoundException
     * @throws ValidationException
     * @throws Throwable
     */
    public function loadTargets() : TargetCollection {
        if (!isset($this->targets)) {
            $this->targets = new TargetCollection();
        }
        $primaryKey = Target::getPrimaryKey();
        $gameId = $this instanceof Game ? $this->id : $this->game->id;
        $date = $this instanceof Game ? $this->start?->format('Y-m-d') : $this->game->start?->format('Y-m-d');
        $query = DB::select(Target::TABLE, '*')
          ->where('%n = %i', $this::getPrimaryKey(), $this->id)
          ->cacheTags(
            'games/'.$this::SYSTEM.'/'.$gameId,
            'games/'.$this::SYSTEM.'/'.$gameId.'/targets',
            'games/'.$date,
            'targets',
            'targets/'.$this::SYSTEM
          );
        if ($this instanceof Team) {
            $query->cacheTags('teams/'.$this::SYSTEM.'/'.$this->id, 'teams/'.$this::SYSTEM.'/'.$this->id.'/targets');
        }
        $rows = $query->fetchAll();
        foreach ($rows as $row) {
            $target = Target::get($row->$primaryKey, $row);
            if ($this instanceof Game) {
                $target->setGame($this);
            }
            else {
                if ($this instanceof Team) { // @phpstan-ignore-line
                    $target->setTeam($this);
                }
            }
            try {
                $this->targets->set($target, (int) $target->identifier);
            } catch (InvalidArgumentException) {

            }
        }
        return $this->targets;
    }
}

<?php

declare(strict_types=1);

namespace App\GameModels\Game;

/**
 * @template P of Player
 * @template T of Team
 * @template G of Game
 */
trait BaseLinkedPlayerProperties
{
    /** @var non-empty-array<P> */
    public array $players;

    /** @var non-empty-array<int|string> */
    public array $vests {
        get {
            if (!isset($this->vests)) {
                $vests = [];
                foreach ($this->players as $player) {
                    $vests[] = $player->vest;
                }
                $this->vests = $vests;
            }
            return $this->vests;
        }
        /**
         * @param non-empty-array<int|string> $value
         */
        set(array $value) {
            $this->vests = $value;
        }
    }

    /**
     * @param non-empty-string $name
     * @param mixed[] $arguments
     */
    public function __call(string $name, array $arguments): void
    {
        foreach ($this->players as $player) {
            if (method_exists($player, $name)) {
                $player->$name(...$arguments);
            }
        }
    }

    public function save(): bool
    {
        $success = true;
        foreach ($this->players as $player) {
            $success = $success && $player->save();
        }
        return $success;
    }

    public function saveHits(): bool
    {
        $success = true;
        foreach ($this->players as $player) {
            $success = $success && $player->saveHits();
        }
        return $success;
    }

    public function delete(): bool
    {
        $success = true;
        foreach ($this->players as $player) {
            $success = $success && $player->delete();
        }
        return $success;
    }

    public function insert(): bool
    {
        $success = true;
        foreach ($this->players as $player) {
            $success = $success && $player->insert();
        }
        return $success;
    }

    public function update(): bool
    {
        $success = true;
        foreach ($this->players as $player) {
            $success = $success && $player->update();
        }
        return $success;
    }

    public function loadHits(): array
    {
        // Load hits for all linked players and merge them
        foreach ($this->players as $player) {
            foreach ($player->getHitsPlayers() as $hit) {
                /** @phpstan-ignore argument.type */
                $this->addHits($hit->playerTarget, $hit->count); // This sums the hits for same targets
            }
        }
        $this->hitPlayers ??= [];
        /** @phpstan-ignore return.type */
        return $this->hitPlayers;
    }

    public function calculateSkill(): int
    {
        // Calculate a weighted skill based on linked players' skills
        $totalSkill = 0;
        $count = 0;
        foreach ($this->players as $player) {
            $totalSkill += $player->calculateSkill();
            $count++;
        }

        return (int)round($totalSkill / $count);
    }

    public function getSkillParts(): array
    {
        $parts = [];
        // Sum all parts
        foreach ($this->players as $player) {
            $playerParts = $player->getSkillParts();
            foreach ($playerParts as $key => $value) {
                $parts[$key] ??= 0.0; // Make sure that the part exists
                $parts[$key] += $value; // Sum
            }
        }

        // Average all parts
        $count = count($this->players);
        foreach ($parts as $key => $value) {
            $parts[$key] = $value / $count;
        }

        return $parts;
    }

    protected function firstWithMemo(string $property): mixed
    {
        $reflection = new \ReflectionProperty($this, $property);
        if (!$reflection->isInitialized($this) || $reflection->isVirtual()) {
            $reflection->setRawValue($this, $this->players[0]->$property ?? null);
        }

        /**
         * @noinspection PhpStrictTypeCheckingInspection
         * @noinspection PhpIncompatibleReturnTypeInspection
         */
        return $reflection->getRawValue($this);
    }

    protected function sumWithMemo(string $property): int
    {
        $reflection = new \ReflectionProperty($this, $property);
        if (!$reflection->isInitialized($this) || $reflection->isVirtual()) {
            $reflection->setRawValue($this, $this->getSum($property));
        }
        /**
         * @noinspection PhpStrictTypeCheckingInspection
         * @noinspection PhpIncompatibleReturnTypeInspection
         */
        return $reflection->getRawValue($this);
    }

    protected function getSum(string $property): int
    {
        $sum = 0;
        foreach ($this->players as $player) {
            $sum += $player->$property;
        }
        return $sum;
    }

    protected function averageWithMemo(string $property): float
    {
        $reflection = new \ReflectionProperty($this, $property);
        if (!$reflection->isInitialized($this) || $reflection->isVirtual()) {
            $reflection->setRawValue($this, $this->getAverage($property));
        }
        /**
         * @noinspection PhpStrictTypeCheckingInspection
         * @noinspection PhpIncompatibleReturnTypeInspection
         */
        return $reflection->getRawValue($this);
    }

    protected function getAverage(string $property): float
    {
        $count = count($this->players);
        /** @phpstan-ignore identical.alwaysFalse */
        if ($count === 0) {
            return 0.0;
        }
        return $this->getSum($property) / $count;
    }
}

<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game\Evo5;

use Dibi\Row;
use Lsr\Core\Models\Interfaces\InsertExtendInterface;

/**
 * Structure containing player's bonuses
 */
class BonusCounts implements InsertExtendInterface
{
    public function __construct(
        public int $agent = 0,
        public int $invisibility = 0,
        public int $machineGun = 0,
        public int $shield = 0,
    ) {
    }

    /**
     * @inheritDoc
     */
    public static function parseRow(Row $row): static {
        /**
         * @noinspection ProperNullCoalescingOperatorUsageInspection
         * @phpstan-ignore-next-line
         */
        return new self(
            $row->bonus_agent ?? 0,
            $row->bonus_invisibility ?? 0,
            $row->bonus_machine_gun ?? 0,
            $row->bonus_shield ?? 0,
        );
    }

    /**
     * Add data from the object into the data array for DB INSERT/UPDATE
     *
     * @param array<string, mixed> $data
     */
    public function addQueryData(array &$data): void {
        $data['bonus_agent'] = $this->agent;
        $data['bonus_invisibility'] = $this->invisibility;
        $data['bonus_machine_gun'] = $this->machineGun;
        $data['bonus_shield'] = $this->shield;
    }

    /**
     * @return array<string,mixed>
     */
    public function getArray(): array {
        $data = [];
        $data['agent'] = $this->agent;
        $data['invisibility'] = $this->invisibility;
        $data['machine_gun'] = $this->machineGun;
        $data['shield'] = $this->shield;
        return $data;
    }

    public function getSum(): int {
        return $this->agent + $this->invisibility + $this->machineGun + $this->shield;
    }
}

<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels;

use App\Models\BaseModel;
use App\Models\System;
use App\Models\SystemType;
use DateTimeImmutable;
use DateTimeInterface;
use Lsr\Db\DB;
use Lsr\LaserLiga\Enums\VestStatus;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\ModelQuery;

#[PrimaryKey('id_vest')]
class Vest extends BaseModel
{
    public const string TABLE = 'system_vests';

    public string $vestNum;
    #[ManyToOne]
    public System $system;
    public ?int $gridCol = null;
    public ?int $gridRow = null;
    public VestStatus $status = VestStatus::OK;
    public ?string $info = null;
    public VestType $type = VestType::VEST;
    public ?DateTimeInterface $updatedAt = null;

    /**
     * @return Vest[]
     * @throws ValidationException
     */
    public static function getForSystem(string | SystemType | System $system) : array {
        return self::querySystem($system)->get();
    }

    /**
     * @return ModelQuery<Vest>
     */
    public static function querySystem(string | SystemType | System $system) : ModelQuery {
        if ($system instanceof System) {
            return self::query()->where('id_system = %s', $system->id);
        }
        if ($system instanceof SystemType) {
            $system = $system->value;
        }
        /** @phpstan-ignore-next-line */
        return self::query()
                   ->where(
                     'id_system IN %sql',
                     DB::select(System::TABLE, 'id_system')->where('type = %s', $system)
                   );
    }

    public static function getVestCount(string | SystemType | System $system) : int {
        return self::querySystem($system)->count();
    }

    public static function getGridCols(string | SystemType | System $system) : int {
        if ($system instanceof SystemType) {
            $system = System::getForType($system);
        }
        else {
            if (is_string($system)) {
                $type = SystemType::tryFrom($system);
                if ($type !== null) {
                    $system = System::getForType($type);
                }
            }
        }

        if (!($system instanceof System)) {
            $system = System::getDefault();
        }
        return DB::select(self::TABLE, 'MAX(grid_col)')->where('id_system = %i', $system->id)->fetchSingle();
    }

    public static function getGridRows(string | SystemType | System $system) : int {
        if ($system instanceof SystemType) {
            $system = System::getForType($system);
        }
        else {
            if (is_string($system)) {
                $type = SystemType::tryFrom($system);
                if ($type !== null) {
                    $system = System::getForType($type);
                }
            }
        }

        if (!($system instanceof System)) {
            $system = System::getDefault();
        }
        return DB::select(self::TABLE, 'MAX(grid_row)')->where('id_system = %i', $system->id)->fetchSingle();
    }

    /**
     * @return object{cols:int,rows:int}|null
     */
    public static function getGridDimensions(string | SystemType | System $system) : ?object {
        if ($system instanceof SystemType) {
            $system = System::getForType($system);
        }
        else {
            if (is_string($system)) {
                $type = SystemType::tryFrom($system);
                if ($type !== null) {
                    $system = System::getForType($type);
                }
            }
        }

        if (!($system instanceof System)) {
            $system = System::getDefault();
        }

        /* @phpstan-ignore-next-line */
        return DB::select(self::TABLE, 'MAX([grid_col]) as [cols], MAX([grid_row]) as [rows]')
                 ->where(
                   '[id_system] = %i',
                   $system->id
                 )
                 ->fetch();
    }

    public function update() : bool {
        $this->updatedAt = new DateTimeImmutable();
        return parent::update();
    }
}

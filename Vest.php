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
use App\Models\Arena;
use Lsr\Db\DB;
use Lsr\LaserLiga\Enums\VestStatus;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\ModelQuery;
use OpenApi\Attributes as OA;

#[PrimaryKey('id_vest'), OA\Schema]
class Vest extends BaseModel
{
    public const string TABLE = 'system_vests';

	#[OA\Property, ManyToOne]
	public Arena $arena;

	#[OA\Property(example: '1')]
	public string             $vestNum;
	#[ManyToOne]
	public System $system;
	#[OA\Property(example: 'evo5')]
	public string             $system;
	#[OA\Property]
	public VestStatus         $status    = VestStatus::OK;
	#[OA\Property(example: 'Zbraň vynechává')]
	public ?string            $info      = null;
	public VestType $type = VestType::VEST;
	#[OA\Property]
	public ?DateTimeInterface $updatedAt = null;

    /**
     * @return Vest[]
     * @throws ValidationException
     */
    public static function getForSystem(string | SystemType | System $system): array {
        return self::querySystem($system)->get();
    }

    /**
     * @return ModelQuery<Vest>
     */
    public static function querySystem(string | SystemType | System $system): ModelQuery {
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
	/**
	 * @param Arena $arena
	 *
	 * @return Vest[]
	 * @throws ValidationException
	 */
	public static function getForArena(Arena $arena): array {
		return self::queryArena($arena)->get();
	}

	/**
	 * @param Arena $arena
	 *
	 * @return ModelQuery<Vest>
	 */
	public static function queryArena(Arena $arena): ModelQuery {
		/** @phpstan-ignore-next-line */
		return self::query()->where('id_arena = %s', $arena->id);
	}

	public function update(): bool {
		$this->updatedAt = new DateTimeImmutable();
		return parent::update();
	}

}

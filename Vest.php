<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels;

use App\GameModels\Game\Enums\VestStatus;
use App\Models\Arena;
use DateTimeImmutable;
use DateTimeInterface;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;
use Lsr\Core\Models\ModelQuery;
use OpenApi\Attributes as OA;

#[PrimaryKey('id_vest'), OA\Schema]
class Vest extends Model
{

	public const TABLE = 'system_vests';

	#[OA\Property, ManyToOne]
	public Arena $arena;

	#[OA\Property(example: '1')]
	public string             $vestNum;
	#[OA\Property(example: 'evo5')]
	public string             $system;
	#[OA\Property]
	public VestStatus         $status    = VestStatus::OK;
	#[OA\Property(example: 'Zbraň vynechává')]
	public ?string            $info      = null;
	#[OA\Property]
	public ?DateTimeInterface $updatedAt = null;

	/**
	 * @param string $system
	 *
	 * @return Vest[]
	 * @throws ValidationException
	 */
	public static function getForSystem(string $system): array {
		return self::querySystem($system)->get();
	}

	/**
	 * @param string $system
	 *
	 * @return ModelQuery<Vest>
	 */
	public static function querySystem(string $system): ModelQuery {
		/** @phpstan-ignore-next-line */
		return self::query()->where('system = %s', $system);
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

	/**
	 * @param string $system
	 *
	 * @return int
	 */
	public static function getVestCount(string $system): int {
		return self::querySystem($system)->count();
	}

	public function update(): bool {
		$this->updatedAt = new DateTimeImmutable();
		return parent::update();
	}

}
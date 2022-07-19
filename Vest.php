<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels;

use App\GameModels\Game\Enums\VestStatus;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;
use Lsr\Core\Models\ModelQuery;

#[PrimaryKey('id_vest')]
class Vest extends Model
{

	public const TABLE      = 'system_vests';
	public const DEFINITION = [
		'vestNum' => ['validators' => ['required'],],
		'system'  => ['validators' => ['required', 'system'],],
		'gridCol' => [],
		'gridRow' => [],
		'status'  => ['class' => VestStatus::class],
		'info'    => [],
	];

	public int        $vestNum;
	public string     $system;
	public ?int       $gridCol = null;
	public ?int       $gridRow = null;
	public VestStatus $status  = VestStatus::OK;
	public ?string    $info    = null;

	/**
	 * @param string $system
	 *
	 * @return Vest[]
	 * @throws ValidationException
	 */
	public static function getForSystem(string $system) : array {
		return self::querySystem($system)->get();
	}

	/**
	 * @param string $system
	 *
	 * @return ModelQuery<Vest>
	 */
	public static function querySystem(string $system) : ModelQuery {
		return self::query()->where('system = %s', $system);
	}

	/**
	 * @param string $system
	 *
	 * @return int
	 */
	public static function getVestCount(string $system) : int {
		return self::querySystem($system)->count();
	}

	public static function getGridCols(string $system) : int {
		/* @phpstan-ignore-next-line */
		return DB::select(self::TABLE, 'MAX(grid_col)')->where('system = %s', $system)->fetchSingle();
	}

	public static function getGridRows(string $system) : int {
		/* @phpstan-ignore-next-line */
		return DB::select(self::TABLE, 'MAX(grid_row)')->where('system = %s', $system)->fetchSingle();
	}

	/**
	 * @param string $system
	 *
	 * @return object{cols:int,rows:int}|null
	 */
	public static function getGridDimensions(string $system) : ?object {
		/* @phpstan-ignore-next-line */
		return DB::select(self::TABLE, 'MAX([grid_col]) as [cols], MAX([grid_row]) as [rows]')->where('[system] = %s', $system)->fetch();
	}

}
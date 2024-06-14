<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game;

use App\GameModels\Game\Enums\PrintOrientation;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;

/**
 * Print template settings
 */
#[PrimaryKey('id_template')]
class PrintTemplate extends Model
{

    public const string TABLE = 'print_templates';

	public string           $slug        = '';
	public string           $name        = '';
	public ?string          $description = '';
	public PrintOrientation $orientation = PrintOrientation::landscape;

	/** @var array<string, PrintTemplate|null> */
	private static $slugCache = [];

	public static function getBySlug(string $slug) : ?PrintTemplate {
		self::$slugCache[$slug] ??= static::query()->where('slug = %s', $slug)->first();
		return self::$slugCache[$slug];
	}

}
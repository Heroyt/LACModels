<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game;

use App\GameModels\Game\Enums\PrintOrientation;
use App\Models\BaseModel;
use Lsr\Orm\Attributes\PrimaryKey;

/**
 * Print template settings
 */
#[PrimaryKey('id_template')]
class PrintTemplate extends BaseModel
{
    public const string TABLE = 'print_templates';
    /** @var array<string, PrintTemplate|null> */
    private static array $slugCache = [];
    public string $slug = '';
    public string $name = '';
    public ?string $description = '';
    public PrintOrientation $orientation = PrintOrientation::landscape;

    public static function getBySlug(string $slug) : ?PrintTemplate {
        self::$slugCache[$slug] ??= static::query()->where('slug = %s', $slug)->first();
        return self::$slugCache[$slug];
    }
}

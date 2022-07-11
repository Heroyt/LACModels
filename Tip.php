<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels;

use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;

#[PrimaryKey('id_tip')]
class Tip extends Model
{

	public const TABLE = 'tips';

	public string $text;

	/**
	 * @return string[]
	 * @throws ValidationException
	 */
	public static function shuffledFormatted() : array {
		$formatted = [];
		foreach (self::shuffled() as $tip) {
			$formatted[] = sprintf(lang('Tip #%d', context: 'tips'), $tip->id).': '.lang($tip->text, context: 'tips');
		}
		return $formatted;
	}

	/**
	 * Get all tips, shuffled (=in random order).
	 *
	 * @return Tip[]
	 * @throws ValidationException
	 */
	public static function shuffled() : array {
		return self::query()->orderBy('RAND()')->get();
	}

	/**
	 * Get one random
	 *
	 * @return Tip|null
	 */
	public static function random() : ?Tip {
		/** @noinspection PhpIncompatibleReturnTypeInspection */
		return self::query()->orderBy('RAND()')->first();
	}

}
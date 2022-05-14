<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */
namespace App\GameModels;

use App\Core\AbstractModel;

class Tip extends AbstractModel
{

	public const TABLE       = 'tips';
	public const PRIMARY_KEY = 'id_tip';
	public const DEFINITION  = [
		'text' => ['validators' => ['required'],],
	];

	public string $text;

	/**
	 * @return string[]
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
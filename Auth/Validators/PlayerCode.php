<?php

namespace App\GameModels\Auth\Validators;

use App\GameModels\Auth\LigaPlayer;
use App\GameModels\Auth\Player;
use Attribute;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\Validation\Validator;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class PlayerCode implements Validator
{

	/**
	 * Validate a value and throw an exception on error
	 *
	 * @param mixed             $value
	 * @param Player|LigaPlayer $class
	 * @param string            $property
	 *
	 * @return void
	 *
	 * @throws ValidationException
	 */
	public function validateValue(mixed $value, object|string $class, string $property) : void {
		$class::validateCode($value, $class);
	}
}
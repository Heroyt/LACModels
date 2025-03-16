<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels;

use App\Models\BaseModel;
use Lsr\Core\App;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\Orm\Attributes\PrimaryKey;

#[PrimaryKey('id_tip')]
class Tip extends BaseModel
{
	public const string TABLE = 'tips';

	public string  $text;
	public ?string $translations = null;

	/** @var array<string,string> */
	private array $translationsParsed;

	/**
	 * @return string[]
	 * @throws ValidationException
	 */
	public static function shuffledFormatted(): array {
		$formatted = [];
		foreach (self::shuffled() as $tip) {
			$formatted[] = sprintf(lang('Tip #%d', domain: 'tips'), $tip->id) . ': ' . $tip->translate();
		}
		return $formatted;
	}

	/**
	 * Get all tips, shuffled (=in random order).
	 *
	 * @return Tip[]
	 * @throws ValidationException
	 */
	public static function shuffled(): array {
		return self::query()->orderBy('RAND()')->get();
	}

	public function translate(?string $lang = null): string {
		if ($lang === null) {
			$lang = App::getInstance()->translations->getLang();
		}
		return $this->getTranslations()[$lang] ?? $this->text;
	}

	/**
	 * @return array<string,string>
	 */
	public function getTranslations(): array {
		if (!isset($this->translationsParsed)) {
			if ($this->translations !== null) {
				$parsed = igbinary_unserialize($this->translations);
				$this->translationsParsed = $parsed === false ? [] : $parsed;
			}
			else {
				$this->translationsParsed = [];
			}
		}
		return $this->translationsParsed;
	}

	/**
	 * Get one random
	 *
	 * @return Tip|null
	 */
	public static function random(): ?Tip {
		return self::query()->orderBy('RAND()')->first();
	}

	public function getQueryData(): array {
		$data = parent::getQueryData();
		$data['translations'] = igbinary_serialize($this->getTranslations());
		return $data;
	}

	public function setTranslation(string $lang, string $text): Tip {
		$this->getTranslations();
		$this->translationsParsed[$lang] = $text;
		return $this;
	}

	public function jsonSerialize(): array {
		$data = parent::jsonSerialize();
		$data['translations'] = $this->getTranslations();
		return $data;
	}
}

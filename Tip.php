<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels;

use App\Core\App;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;

#[PrimaryKey('id_tip')]
class Tip extends Model
{
    public const string TABLE = 'tips';

    public string $text;
    public ?string $translations = null;

    /** @var array<string,string>  */
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

    /**
     * @return array<string,string>
     */
    public function getTranslations(): array {
        if (!isset($this->translationsParsed)) {
            if ($this->translations !== null) {
                $this->translationsParsed = igbinary_unserialize($this->translations);
            } else {
                $this->translationsParsed = [];
            }
        }
        return $this->translationsParsed;
    }

    public function setTranslation(string $lang, string $text): Tip {
        $this->getTranslations();
        $this->translationsParsed[$lang] = $text;
        return $this;
    }

    public function translate(?string $lang = null): string {
        if ($lang === null) {
            $lang = App::getInstance()->translations->getLang();
        }
        return $this->getTranslations()[$lang] ?? $this->text;
    }

    public function jsonSerialize(): array {
        $data = parent::jsonSerialize();
        $data['translations'] = $this->getTranslations();
        return $data;
    }
}

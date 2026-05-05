<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels;

use App\Core\App;
use App\Models\BaseModel;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Transform;

#[PrimaryKey('id_tip')]
class Tip extends BaseModel
{
    public const string TABLE = 'tips';

    public string $text;
    #[Transform(save: 'transformTranslationsForSave', load: 'transformTranslationsForLoad')]
    public ?string $translations = null;

    /** @var array<string,string> */
    private array $translationsParsed;

    /**
     * @return string[]
     * @throws ValidationException
     */
    public static function shuffledFormatted() : array {
        $formatted = [];
        foreach (self::shuffled() as $tip) {
            $formatted[] = sprintf(lang('Tip #%d', domain: 'tips'), $tip->id).': '.$tip->translate();
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

    public function translate(?string $lang = null) : string {
        if ($lang === null) {
            $lang = App::getInstance()->translations->getLang();
        }
        return $this->getTranslations()[$lang] ?? $this->text;
    }

    /**
     * @return array<string,string>
     */
    public function getTranslations() : array {
        if (!isset($this->translationsParsed)) {
            if ($this->translations !== null) {
                $translations = $this->unserializeTranslations($this->translations);
                $this->translationsParsed = $translations === false ? [] : $translations;
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
    public static function random() : ?Tip {
        return self::query()->orderBy('RAND()')->first();
    }

    public function getQueryData(bool $filterChanged = true) : array {
        $data = parent::getQueryData($filterChanged);
        $data['translations'] = igbinary_serialize($this->getTranslations());
        return $data;
    }

    public function setTranslation(string $lang, string $text) : Tip {
        $this->getTranslations();
        $this->translationsParsed[$lang] = $text;
        return $this;
    }

    protected function transformTranslationsForSave(?string $translations): ?string
    {
        if ($translations === null) {
            return null;
        }
        return base64_encode($translations);
    }

    protected function transformTranslationsForLoad(?string $translations): ?string
    {
        if ($translations === null) {
            return null;
        }
        $decoded = base64_decode($translations, true);
        if ($decoded === false) {
            return $translations;
        }
        if ($this->canUnserializeTranslations($decoded)) {
            return $decoded;
        }
        return $translations;
    }

    /**
     * @return array<string,string>|false
     */
    private function unserializeTranslations(string $translations): array|false
    {
        $decoded = base64_decode($translations, true);
        if ($decoded !== false && $this->canUnserializeTranslations($decoded)) {
            return igbinary_unserialize($decoded);
        }
        return igbinary_unserialize($translations);
    }

    private function canUnserializeTranslations(string $value): bool
    {
        $unserialized = @igbinary_unserialize($value);
        return !(
            ($unserialized === false && $value !== igbinary_serialize(false)) ||
            ($unserialized === null && $value !== igbinary_serialize(null))
        );
    }

    public function jsonSerialize() : array {
        $data = parent::jsonSerialize();
        $data['translations'] = $this->getTranslations();
        return $data;
    }
}

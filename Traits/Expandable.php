<?php

namespace App\GameModels\Traits;

use App\Core\App;
use LAC\Modules\Core\GameDataExtensionInterface;
use Lsr\Orm\Model;

trait Expandable
{
    /** @var GameDataExtensionInterface[] */
    protected static array $extensions;

    /** @var array<string, list<callable(Model $model, mixed ...$args):void>> */
    protected array $hooks = [];

    /** @var array<string, Model> */
    protected array $data = [];

    /**
     * @param  string  $name
     * @param  callable(Model $model, mixed ...$args):void  $callable
     * @return void
     */
    public function hook(string $name, callable $callable) : void {
        $this->hooks[$name] ??= [];
        $this->hooks[$name][] = $callable;
    }

    /**
     * @param  string  $name
     * @return Model|null
     */
    public function __get($name) : ?Model {
        return $this->data[$name] ?? null;
    }

    /**
     * @param  string  $name
     * @param  Model  $value
     * @return void
     */
    public function __set($name, ?Model $value) : void {
        $this->data[$name] = $value;
    }

    /**
     * @param  string  $name
     * @return bool
     */
    public function __isset($name) : bool {
        return isset($this->data[$name]);
    }

    protected function initExtensions() : void {
        foreach (static::getExtensions() as $extension) {
            $extension->init($this);
        }
    }

    /**
     * @return GameDataExtensionInterface[]
     */
    public static function getExtensions() : array {
        if (!isset(static::$extensions)) {
            static::$extensions = [];
            $names = App::getContainer()->findByTag(static::DI_TAG);
            foreach ($names as $name => $arguments) {
                $extension = App::getService($name);
                assert($extension instanceof GameDataExtensionInterface);
                static::$extensions[] = $extension;
            }
        }
        return static::$extensions;
    }

    protected function extensionSave() : bool {
        $success = true;
        foreach (static::getExtensions() as $extension) {
            $success = $success && $extension->save($this);
        }
        return $success;
    }

    /**
     * @param  array<string,mixed>  $data
     * @return void
     */
    protected function extensionJson(array &$data) : void {
        foreach (static::getExtensions() as $extension) {
            $extension->addJsonData($data, $this);
        }
    }

    protected function extensionFillFromRow() : void {
        foreach (static::getExtensions() as $extension) {
            $extension->parseRow($this->row, $this);
        }
    }

    /**
     * @param  array<string,mixed>  $data
     * @return void
     */
    protected function extensionAddQueryData(array &$data) : void {
        foreach (static::getExtensions() as $extension) {
            $extension->addQueryData($data, $this);
        }
    }

    protected function runHook(string $name, mixed ...$args) : void {
        foreach ($this->hooks[$name] ?? [] as $callable) {
            $callable($this, ...$args);
        }
    }
}

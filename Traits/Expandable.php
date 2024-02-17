<?php

namespace App\GameModels\Traits;

use App\Core\App;
use LAC\Modules\Core\GameDataExtensionInterface;
use Lsr\Core\Models\Model;

trait Expandable
{

	/** @var GameDataExtensionInterface[] */
	protected static array $extensions;

	/** @var array<string, callable> */
	protected array $hooks = [];

	/** @var array<string, Model> */
	protected array $data = [];

	public function hook(string $name, callable $callable): void {
		if (!isset($this->hooks[$name])) {
			$this->hooks[$name] = [];
		}
		$this->hooks[$name][] = $callable;
	}

	/**
	 * @param string $name
	 * @return Model|null
	 */
	public function __get($name): ?Model {
		return $this->data[$name] ?? null;
	}

	/**
	 * @param string $name
	 * @param Model $value
	 * @return void
	 */
	public function __set($name, ?Model $value): void {
		$this->data[$name] = $value;
	}

	/**
	 * @param string $name
	 * @return bool
	 */
	public function __isset($name): bool {
		return isset($this->data[$name]);
	}

	protected function initExtensions(): void {
		foreach (static::getExtensions() as $extension) {
			$extension->init($this);
		}
	}

	/**
	 * @return GameDataExtensionInterface[]
	 */
	public static function getExtensions(): array {
		if (!isset(static::$extensions)) {
			static::$extensions = [];
			$names = App::getContainer()->findByTag(static::DI_TAG);
			foreach ($names as $name => $arguments) {
				// @phpstan-ignore-next-line
				static::$extensions[] = App::getService($name);
			}
		}
		return static::$extensions;
	}

	protected function extensionSave(): bool {
		$success = true;
		foreach (static::getExtensions() as $extension) {
			$success = $success && $extension->save($this);
		}
		return $success;
	}

	protected function extensionJson(array &$data): void {
		foreach (static::getExtensions() as $extension) {
			$extension->addJsonData($data, $this);
		}
	}

	protected function extensionFillFromRow(): void {
		foreach (static::getExtensions() as $extension) {
			$extension->parseRow($this->row, $this);
		}
	}

	protected function extensionAddQueryData(array &$data): void {
		foreach (static::getExtensions() as $extension) {
			$extension->addQueryData($data, $this);
		}
	}

	protected function runHook(string $name, ...$args): void {
		foreach ($this->hooks[$name] ?? [] as $callable) {
			$callable($this, ...$args);
		}
	}

}
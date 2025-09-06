<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Field;

use Shredio\RequestMapper\Exception\FieldNotExistsException;

final readonly class Field
{

	private function __construct(
		public string $name,
		private ?string $parentPath = null,
	)
	{
	}

	public function getFullPath(): string
	{
		return $this->parentPath === null ? $this->name : $this->parentPath . '.' . $this->name;
	}

	public function getParentPath(): ?string
	{
		return $this->parentPath;
	}

	public function hasParent(): bool
	{
		return $this->parentPath !== null;
	}

	public static function create(string $path): self
	{
		$pos = strrpos($path, '.');
		if ($pos === false) {
			$name = $path;
			$parentPath = null;
		} else {
			$name = substr($path, $pos + 1);
			$parentPath = substr($path, 0, $pos);
		}

		return new self($name, $parentPath);
	}

	/**
	 * @return list<string>
	 */
	private function getChoppedParentPath(): array
	{
		if ($this->parentPath === null) {
			return [];
		}

		return explode('.', $this->parentPath);
	}

	/**
	 * @param mixed[] $values
	 * @throws FieldNotExistsException
	 */
	public function getValueFrom(array $values): mixed
	{
		return $this->getReferenceValueFrom($values);
	}

	/**
	 * @param mixed[] $values
	 */
	public function existsIn(array $values): bool
	{
		$current = $values;
		$choppedPath = $this->getChoppedParentPath();
		foreach ($choppedPath as $part) {
			if (!is_array($current) || !array_key_exists($part, $current)) {
				return false;
			}
			$current = $current[$part];
		}

		return is_array($current) && array_key_exists($this->name, $current);
	}

	/**
	 * @param mixed[] $values
	 * @throws FieldNotExistsException
	 */
	public function &getReferenceValueFrom(array &$values): mixed
	{
		$current = &$values;
		$choppedPath = $this->getChoppedParentPath();
		foreach ($choppedPath as $i => $part) {
			if (!is_array($current) || !array_key_exists($part, $current)) {
				throw new FieldNotExistsException(sprintf(
					'Failed to get value for field "%s": path "%s" does not exist.',
					$this->getFullPath(),
					implode('.', array_slice($choppedPath, 0, $i)),
				));
			}
			$current = &$current[$part];
		}

		if (!is_array($current) || !array_key_exists($this->name, $current)) {
			throw new FieldNotExistsException(sprintf(
				'Failed to get value for field "%s": path "%s" does not exist.',
				$this->getFullPath(),
				$this->getFullPath(),
			));
		}

		return $current[$this->name];
	}

	/**
	 * @param mixed[] $values
	 */
	public function &getForcedReferenceValueFrom(array &$values): mixed
	{
		$current = &$values;
		$choppedPath = $this->getChoppedParentPath();
		foreach ($choppedPath as $part) {
			if (!is_array($current)) { // @phpstan-ignore function.alreadyNarrowedType
				$current = [];
			}
			if (!array_key_exists($part, $current) || !is_array($current[$part])) {
				$current[$part] = [];
			}
			$current = &$current[$part];
		}

		if (!is_array($current)) { // @phpstan-ignore function.alreadyNarrowedType
			$current = [];
		}
		if (!array_key_exists($this->name, $current)) {
			$current[$this->name] = null;
		}

		return $current[$this->name];
	}

	/**
	 * @param mixed[] $values
	 */
	public function unsetValueFrom(array &$values): void
	{
		$current = &$values;
		$choppedPath = $this->getChoppedParentPath();
		foreach ($choppedPath as $part) {
			if (!is_array($current) || !array_key_exists($part, $current)) {
				return; // nothing to unset
			}
			$current = &$current[$part];
		}

		if (!is_array($current) || !array_key_exists($this->name, $current)) {
			return; // nothing to unset
		}

		unset($current[$this->name]);
	}

}

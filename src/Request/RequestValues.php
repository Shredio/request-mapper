<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Request;

use Shredio\RequestMapper\Exception\FieldNotExistsException;
use Shredio\RequestMapper\Field\Field;

final class RequestValues
{

	/**
	 * @param mixed[] $values
	 */
	public function __construct(
		private array $values,
		public bool $needToUseObjectMapper,
	)
	{
	}

	public function set(Field|string $field, mixed $value): void
	{
		if (is_string($field)) {
			$this->values[$field] = $value;
		} else {
			$val = &$field->getForcedReferenceValueFrom($this->values);
			$val = $value;
		}
	}

	public function get(Field|string $field): mixed
	{
		if (is_string($field)) {
			return $this->values[$field] ?? null;
		}

		try {
			return $field->getValueFrom($this->values);
		} catch (FieldNotExistsException) {
			return null;
		}
	}

	public function has(Field|string $field): bool
	{
		if (is_string($field)) {
			return array_key_exists($field, $this->values);
		}

		return $field->existsIn($this->values);
	}

	/**
	 * @return mixed[]
	 */
	public function all(): array
	{
		return $this->values;
	}

}

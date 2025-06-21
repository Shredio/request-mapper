<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Request;

readonly class RequestValues
{

	/**
	 * @param mixed[] $values
	 */
	public function __construct(
		public array $values,
		public bool $typeless = false,
	)
	{
	}

	public function hasKey(string $key): bool
	{
		return array_key_exists($key, $this->values);
	}

	public function getValue(string $key): mixed
	{
		return $this->values[$key] ?? null;
	}

}

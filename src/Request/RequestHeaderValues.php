<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Request;

final readonly class RequestHeaderValues extends RequestValues
{

	public function hasKey(string $key): bool
	{
		return parent::hasKey(strtolower($key));
	}

	public function getValue(string $key): mixed
	{
		$value = parent::getValue(strtolower($key));
		if (is_array($value)) {
			return $value[0] ?? null;
		}

		return $value;
	}

}

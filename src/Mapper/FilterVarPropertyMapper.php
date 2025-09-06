<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Mapper;

use Shredio\Problem\Exception\ViolationAwareException;
use Shredio\Problem\Violation\Violation;
use Shredio\RequestMapper\Error\RequestMapperErrorCollector;
use Shredio\RequestMapper\Exception\InvalidPropertyException;
use Shredio\RequestMapper\Filter\FilterVar;

/**
 * @internal
 */
final readonly class FilterVarPropertyMapper implements PropertyMapper
{

	public function __construct(
		private FilterVar $filter,
	)
	{
	}

	public function tryToConvertToScalar(mixed $value, string $type): mixed
	{
		return $this->filter->call($value, true)->value;
	}

	public function convert(mixed $value, string $type): mixed
	{
		$result = $this->filter->call($value);
		if ($result->isValid) {
			return $result->value;
		}

		throw new InvalidPropertyException(
			fn (string $fieldName): Violation => $result->getViolation($fieldName) ?: $this->filter->createViolation($fieldName),
		);
	}

}

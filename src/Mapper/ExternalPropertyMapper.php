<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Mapper;

use Shredio\Problem\Exception\ViolationAwareException;

/**
 * @internal
 */
final readonly class ExternalPropertyMapper implements PropertyMapper
{

	/**
	 * @param RequestValueMapper<object> $mapper
	 */
	public function __construct(
		private RequestValueMapper $mapper,
	)
	{
	}

	public function tryToConvertToScalar(mixed $value, string $type): mixed
	{
		return $this->mapper->convert($value, $type); // @phpstan-ignore argument.type
	}

	/**
	 * @throws ViolationAwareException
	 */
	public function convert(mixed $value, string $type): object
	{
		return $this->mapper->map($value, $type); // @phpstan-ignore argument.type
	}

}

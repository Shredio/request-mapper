<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Mapper;

use Shredio\Problem\Exception\ViolationAwareException;
use Shredio\RequestMapper\Exception\InvalidPropertyException;

/**
 * @internal
 */
interface PropertyMapper
{

	public function tryToConvertToScalar(mixed $value, string $type): mixed;

	/**
	 * @throws ViolationAwareException
	 * @throws InvalidPropertyException
	 */
	public function convert(mixed $value, string $type): mixed;

}

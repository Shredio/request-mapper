<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Mapper;

use Shredio\Problem\Exception\ViolationAwareException;

/**
 * @template TValue of object
 */
interface RequestValueMapper
{

	/**
	 * @return class-string<TValue>
	 */
	public function getSupportedType(): string;

	/**
	 * @param class-string<TValue> $className
	 * @return TValue
	 * @throws ViolationAwareException in case of validation errors
	 */
	public function map(mixed $input, string $className): object;

	/**
	 * @param class-string<TValue> $className
	 */
	public function convert(mixed $input, string $className): mixed;

}

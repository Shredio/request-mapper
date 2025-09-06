<?php declare(strict_types = 1);

namespace Shredio\RequestMapper;

use Shredio\RequestMapper\Request\Exception\InvalidRequestException;

interface RequestObjectMapper
{

	/**
	 * @template T of object
	 * @param class-string<T> $className
	 * @param mixed[] $values
	 * @return T
	 *
	 * @throws InvalidRequestException
	 */
	public function map(string $className, array $values, RequestObjectMapperContext $context): object;

}

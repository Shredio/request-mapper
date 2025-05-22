<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Mapper;

use Shredio\RequestMapper\Exception\ObjectMappingFailedException;

interface ObjectMapper
{

	/**
	 * @template T of object
	 * @param class-string<T>|T $target
	 * @return T
	 *
	 * @throws ObjectMappingFailedException
	 */
	public function map(object $source, string|object $target): object;

}

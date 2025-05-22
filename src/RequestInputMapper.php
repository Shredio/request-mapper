<?php declare(strict_types = 1);

namespace Shredio\RequestMapper;

use Shredio\RequestMapper\Context\RequestMappingContext;

interface RequestInputMapper
{

	/**
	 * @template T of object
	 * @param class-string<T> $target
	 * @return T
	 */
	public function map(string $target, RequestMappingContext $context): object;

}

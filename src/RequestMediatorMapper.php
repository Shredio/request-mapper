<?php declare(strict_types = 1);

namespace Shredio\RequestMapper;

interface RequestMediatorMapper
{

	/**
	 * @template T of object
	 * @param class-string<T> $target
	 * @return T
	 */
	public function map(object $source, string $target): object;

}

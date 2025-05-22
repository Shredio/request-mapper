<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Mapper;

use AutoMapper\AutoMapperInterface;

/**
 * @see https://automapper.jolicode.com/latest/
 */
final readonly class JoliCodeObjectMapper implements ObjectMapper
{

	public function __construct(
		private AutoMapperInterface $mapper,
	)
	{
	}

	/**
	 * @template T of object
	 * @param class-string<T>|T $target
	 * @return T
	 */
	public function map(object $source, string|object $target): object
	{
		/** @var T */
		return $this->mapper->map($source, $target);
	}

}

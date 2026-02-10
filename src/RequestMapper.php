<?php declare(strict_types = 1);

namespace Shredio\RequestMapper;

use Shredio\RequestMapper\Request\Exception\InvalidRequestException;
use Shredio\TypeSchema\Types\Type;

interface RequestMapper
{

	/**
	 * @template T of object
	 * @param class-string<T> $target
	 * @return T
	 *
	 * @throws InvalidRequestException
	 */
	public function mapToObject(string $target, ?RequestMapperConfiguration $configuration = null): object;

	/**
	 * @template T of array<string, mixed>
	 * @param Type<T> $type
	 * @return T
	 *
	 * @throws InvalidRequestException
	 */
	public function mapToArray(Type $type, ?RequestMapperConfiguration $configuration = null): array;

}

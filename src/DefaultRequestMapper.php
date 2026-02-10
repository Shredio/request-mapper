<?php declare(strict_types = 1);

namespace Shredio\RequestMapper;

use Shredio\RequestMapper\Request\Exception\InvalidRequestException;
use Shredio\RequestMapper\Request\RequestContextFactory;
use Shredio\TypeSchema\Types\Type;

final readonly class DefaultRequestMapper implements RequestMapper
{

	public function __construct(
		private RequestParameterMapper $requestParameterMapper,
		private RequestContextFactory $requestContextFactory,
	)
	{
	}

	/**
	 * @template T of object
	 * @param class-string<T> $target
	 * @return T
	 *
	 * @throws InvalidRequestException
	 */
	public function mapToObject(string $target, ?RequestMapperConfiguration $configuration = null): object
	{
		return $this->requestParameterMapper->mapToObject(
			$target,
			$this->requestContextFactory->create($configuration),
		);
	}

	/**
	 * @template T of array<string, mixed>
	 * @param Type<T> $type
	 * @return T
	 *
	 * @throws InvalidRequestException
	 */
	public function mapToArray(Type $type, ?RequestMapperConfiguration $configuration = null): array
	{
		return $this->requestParameterMapper->mapToArray(
			$type,
			$this->requestContextFactory->create($configuration),
		);
	}

}

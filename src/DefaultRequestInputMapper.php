<?php declare(strict_types = 1);

namespace Shredio\RequestMapper;

use ShipMonk\InputMapper\Runtime\Exception\MappingFailedException;
use ShipMonk\InputMapper\Runtime\MapperProvider;
use Shredio\RequestMapper\Context\RequestMappingContext;
use Shredio\RequestMapper\Exception\InvalidRequestException;
use Shredio\RequestMapper\Mapper\ObjectMapper;

final readonly class DefaultRequestInputMapper implements RequestInputMapper
{

	private MapperProvider $mapperProvider;

	public function __construct(
		private ObjectMapper $objectMapper,
		string $tempDir,
		bool $autoRefresh = false,
	)
	{
		$this->mapperProvider = new MapperProvider($tempDir, $autoRefresh);
	}

	/**
	 * @template T of object
	 * @param class-string<T> $target
	 * @return T
	 *
	 * @throws InvalidRequestException
	 */
	public function map(string $target, RequestMappingContext $context): object
	{
		$values = $context->getRequestValues();
		$mediator = $context->getMediatorClass();

		foreach ($context->getRequestParameters() as $parameter) {
			if ($parameter->location) {
				$bag = $context->getRequestValuesByLocation($parameter->location);
				$key = $parameter->sourceKey ?? $parameter->property;

				if (array_key_exists($key, $bag)) {
					$values[$parameter->property] = $bag[$key];
				} else {
					unset($values[$parameter->property]); // override the value and eventually exception will be thrown
				}
			} else if ($parameter->sourceKey) {
				if (array_key_exists($parameter->sourceKey, $values)) {
					$values[$parameter->property] = $values[$parameter->sourceKey];
				} else {
					unset($values[$parameter->property]); // override the value and eventually exception will be thrown
				}
			}
		}

		try {
			$output = $this->mapperProvider->get($mediator ?? $target)->map($values);

			if ($mediator === null) {
				/** @var T */
				return $output;
			}

			/** @var T */
			return $this->objectMapper->map($output, $target);
		} catch (MappingFailedException $exception) {
			throw new InvalidRequestException($exception);
		}
	}

}

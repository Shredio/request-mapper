<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Mapper\Shipmonk;

use ShipMonk\InputMapper\Runtime\Exception\MappingFailedException;
use ShipMonk\InputMapper\Runtime\MapperProvider;
use Shredio\Problem\Violation\FieldViolation;
use Shredio\RequestMapper\Error\RequestMapperErrorFactory;
use Shredio\RequestMapper\Request\Exception\InvalidRequestException;
use Shredio\RequestMapper\RequestObjectMapper;
use Shredio\RequestMapper\RequestObjectMapperContext;

final readonly class ShipmonkRequestObjectMapper implements RequestObjectMapper
{

	public function __construct(
		private MapperProvider $provider,
	)
	{
	}

	public function map(string $className, array $values, RequestObjectMapperContext $context): object
	{
		try {
			return $this->provider->get($className)->map($values);
		} catch (MappingFailedException $exception) {
			$message = $exception->getMessage();
			$path = $exception->getPath();
			$pos = strpos($message, ':'); // Trim "Failed to map data at path /:"
			if ($pos !== false) {
				$message = ltrim(substr($message, $pos + 1));
			}

			$violations = [];

			if (str_starts_with($message, 'Missing required key')) { // missingKey
				if (preg_match('/"([^"]+)"$/', $message, $matches)) {
					$field = $this->addPathToField($matches[1], $path);
				} else {
					$field = implode('.', $path);
				}

				$violations[] = RequestMapperErrorFactory::missingField($context->getCorrectParameterName($field));
			} else if (str_starts_with($message, 'Unrecognized key')) { // extraKeys
				if (preg_match_all('/"([^"]+)"/', $message, $matches)) {
					foreach ($matches[1] as $field) {
						$violations[] = RequestMapperErrorFactory::extraField($context->getCorrectParameterName($this->addPathToField($field, $path)));
					}
				} else {
					$field = implode('.', $path);
					$violations[] = new FieldViolation($context->getCorrectParameterName($field), [$this->processOriginalMessage($message)]);
				}
			} else { // incorrectType, incorrectValue, duplicateValue
				$field = implode('.', $path);
				$violations[] = new FieldViolation($context->getCorrectParameterName($field), [$this->processOriginalMessage($message)]);
			}

			throw new InvalidRequestException($className, $violations, $exception);
		}
	}

	private function processOriginalMessage(string $message): string
	{
		if (!str_ends_with($message, '.')) {
			$message .= '.';
		}

		return $message;
	}

	/**
	 * @param list<string|int> $path
	 */
	private function addPathToField(string $field, array $path): string
	{
		return $path === [] ? $field : implode('.', $path) . '.' . $field;
	}

}

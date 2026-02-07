<?php declare(strict_types = 1);

namespace Shredio\RequestMapper;

use Shredio\Problem\Violation\FieldViolation;
use Shredio\Problem\Violation\GlobalViolation;
use Shredio\Problem\Violation\Violation;
use Shredio\RequestMapper\Attribute\RequestParam;
use Shredio\RequestMapper\Conversion\ConversionType;
use Shredio\RequestMapper\Request\Exception\InvalidRequestException;
use Shredio\RequestMapper\Request\RequestContext;
use Shredio\RequestMapper\Request\RequestLocation;
use Shredio\RequestMapper\Request\SingleRequestParameter;
use Shredio\TypeSchema\Config\TypeConfig;
use Shredio\TypeSchema\Config\TypeHierarchyConfig;
use Shredio\TypeSchema\Conversion\ConversionStrategy;
use Shredio\TypeSchema\Conversion\ConversionStrategyFactory;
use Shredio\TypeSchema\Error\ErrorElement;
use Shredio\TypeSchema\Error\ErrorReportConfig;
use Shredio\TypeSchema\TypeSchema;
use Shredio\TypeSchema\TypeSchemaProcessor;

final readonly class RequestMapper
{

	private ErrorReportConfig $errorReportConfig;

	public function __construct(
		private TypeSchemaProcessor $typeSchemaProcessor,
		?ErrorReportConfig $errorReportConfig = null,
	)
	{
		$this->errorReportConfig = $errorReportConfig ?? new ErrorReportConfig(
			exposeExpectedType: false,
		);
	}

	/**
	 * @param class-string $controllerName
	 *
	 * @throws InvalidRequestException
	 */
	public function mapSingleParam(SingleRequestParameter $requestParam, string $controllerName, RequestParam $requestParamConfig, RequestContext $context): mixed
	{
		$paramLocation = $requestParamConfig->location ?? $context->getDefaultRequestLocation();
		$requestValues = $context->getRequestValuesByLocation($paramLocation);
		$conversionType = $this->getConversionTypeByLocation($paramLocation);
		$conversionStrategy = $this->getConversionStrategyForTypeSchema($conversionType);

		[$value, $isSet] = $this->processParamConfig(
			$requestParam->name,
			$requestParamConfig,
			$requestValues,
			$paramLocation,
			$conversionType,
			$context,
		);

		$values = [];
		if ($isSet) {
			$values[$requestParam->name] = $value;
		} else if ($requestParam->isOptional) {
			return $requestParam->defaultValue;
		}

		$value = $this->typeSchemaProcessor->parse(
			$values,
			TypeSchema::get()->arrayShape([
				$requestParam->name => $requestParam->typeSchema,
			]),
			new TypeConfig($conversionStrategy),
			collectErrors: true,
		);

		if ($value instanceof ErrorElement) {
			throw new InvalidRequestException($controllerName, $this->createViolations($value));
		}

		return $value[$requestParam->name];
	}

	/**
	 * @template T of object
	 * @param class-string<T> $target
	 * @return T
	 *
	 * @throws InvalidRequestException
	 */
	public function map(string $target, RequestContext $context): object
	{
		[$requestValues, $typeConfig, $keysToReindex] = $this->getRequestValuesAndTypeConfig($context);

		$value = $this->typeSchemaProcessor->parse(
			$requestValues,
			TypeSchema::get()->mapper($target),
			$typeConfig,
			collectErrors: true,
		);
		if ($value instanceof ErrorElement) {
			throw new InvalidRequestException($target, $this->createViolations($value, $keysToReindex));
		}

		return $value;
	}

	/**
	 * @param mixed[] $requestValues
	 * @return array{ mixed, bool, TypeConfig|null, non-empty-string|null }
	 */
	private function processParamConfig(
		string $originalName,
		RequestParam|RequestLocation $paramConfig,
		array $requestValues,
		RequestLocation $defaultLocation,
		ConversionType $defaultConversionType,
		RequestContext $context,
	): array
	{
		$paramConfig = $paramConfig instanceof RequestLocation ? new RequestParam(location: $paramConfig) : $paramConfig;
		$sourceKey = $paramConfig->sourceKey === null ? $originalName : $paramConfig->sourceKey;
		$paramValues = $requestValues;
		$typeConfig = null;
		$value = null;
		$keyToReindex = null;

		if ($paramConfig->location !== null && $paramConfig->location !== $defaultLocation) {
			$paramConversionType = $this->getConversionTypeByLocation($paramConfig->location);
			if ($paramConversionType !== $defaultConversionType) {
				$typeConfig = new TypeConfig(
					conversionStrategy: $this->getConversionStrategyForTypeSchema($paramConversionType),
				);
			}

			$paramValues = $context->getRequestValuesByLocation($paramConfig->location);
		}

		$paramLocation = $paramConfig->location ?? $defaultLocation;
		$normalizedKey = $context->normalizeKey($sourceKey, $paramLocation);
		if (array_key_exists($normalizedKey, $paramValues)) {
			$value = $context->filterValue($paramValues[$normalizedKey], $paramLocation);
			$isSet = true;
		} else {
			$isSet = false;
		}

		if ($paramConfig->sourceKey !== null) {
			$keyToReindex = $paramConfig->sourceKey;
		}

		return [$value, $isSet, $typeConfig, $keyToReindex];
	}

	/**
	 * @return array{ mixed[], TypeConfig, array<non-empty-string, non-empty-string> }
	 */
	private function getRequestValuesAndTypeConfig(RequestContext $context): array
	{
		$defaultLocation = $context->getDefaultRequestLocation();
		$defaultConversionType = $this->getConversionTypeByLocation($defaultLocation);
		$defaultConversionStrategy = $this->getConversionStrategyForTypeSchema($defaultConversionType);

		$requestValues = $context->getRequestValues();
		$hierarchyConfigs = [];
		$keysToReindex = [];
		foreach ($context->getParamConfigs() as $paramKey => $paramConfig) {
			[$value, $isSet, $typeConfig, $keyToReindex] = $this->processParamConfig(
				$paramKey,
				$paramConfig,
				$requestValues,
				$defaultLocation,
				$defaultConversionType,
				$context,
			);

			if ($isSet) {
				$requestValues[$paramKey] = $value;
			} else {
				unset($requestValues[$paramKey]); // ensure missing keys are not present
			}

			if ($typeConfig !== null) {
				$hierarchyConfigs[$paramKey] = $typeConfig;
			}

			if ($keyToReindex !== null) {
				$keysToReindex[$paramKey] = $keyToReindex;
			}
		}

		$typeConfig = new TypeConfig(
			conversionStrategy: $defaultConversionStrategy,
			hierarchyConfig: TypeHierarchyConfig::fromArray($hierarchyConfigs),
		);

		return [$requestValues, $typeConfig, $keysToReindex];
	}

	private function getConversionStrategyForTypeSchema(ConversionType $type): ConversionStrategy
	{
		return match ($type) {
			ConversionType::TypeStrict => ConversionStrategyFactory::json(),
			ConversionType::TypeLenient => ConversionStrategyFactory::httpGet(),
		};
	}

	private function getConversionTypeByLocation(RequestLocation $location): ConversionType
	{
		return match ($location) {
			RequestLocation::Body => ConversionType::TypeStrict,
			default => ConversionType::TypeLenient,
		};
	}

	/**
	 * @param array<non-empty-string, non-empty-string> $keysToReindex
	 * @return non-empty-list<Violation>
	 */
	private function createViolations(ErrorElement $error, array $keysToReindex = []): array
	{
		$grouped = [];
		$global = [];
		foreach ($error->getReports(config: $this->errorReportConfig) as $report) {
			$path = $report->toArrayPath();
			$count = count($path);
			if ($count === 0) {
				$global[] = (string) $report->message;
				continue;
			}

			if ($count === 1) {
				$path[0] = $key = $keysToReindex[$path[0]] ?? $path[0];
			} else {
				$key = implode('.', $path);
			}

			$grouped[$key] ??= [
				'path' => $path,
				'messages' => [],
			];

			$grouped[$key]['messages'][] = $report->message;
		}

		$violations = [];
		if ($global !== []) {
			$violations[] = new GlobalViolation($global);
		}
		foreach ($grouped as $group) {
			$violations[] = new FieldViolation($group['path'], $group['messages']);
		}

		return $violations; // @phpstan-ignore return.type (the list is always non-empty here)
	}

}

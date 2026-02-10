<?php declare(strict_types = 1);

namespace Shredio\RequestMapper;

use Shredio\Problem\Violation\FieldViolation;
use Shredio\Problem\Violation\GlobalViolation;
use Shredio\Problem\Violation\Violation;
use Shredio\RequestMapper\Attribute\RequestParam;
use Shredio\RequestMapper\Conversion\ConversionType;
use Shredio\RequestMapper\Exception\LogicException;
use Shredio\RequestMapper\Request\Exception\InvalidRequestException;
use Shredio\RequestMapper\Request\RequestContext;
use Shredio\RequestMapper\Request\RequestLocation;
use Shredio\TypeSchema\Config\TypeConfig;
use Shredio\TypeSchema\Config\TypeHierarchyConfig;
use Shredio\TypeSchema\Conversion\ConversionStrategy;
use Shredio\TypeSchema\Conversion\ConversionStrategyFactory;
use Shredio\TypeSchema\Enum\ExtraKeysBehavior;
use Shredio\TypeSchema\Error\ErrorElement;
use Shredio\TypeSchema\Error\ErrorReport;
use Shredio\TypeSchema\Error\ErrorReportConfig;
use Shredio\TypeSchema\Error\Path;
use Shredio\TypeSchema\Types\Type;
use Shredio\TypeSchema\TypeSchema;
use Shredio\TypeSchema\TypeSchemaProcessor;

/**
 * @internal
 */
final readonly class RequestParameterMapper
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
	 * @template T of array<string, mixed>
	 * @param Type<T> $type
	 * @return T
	 *
	 * @throws InvalidRequestException
	 */
	public function mapToArray(Type $type, RequestContext $context): array
	{
		return $this->mapToType($type, $context, 'array');
	}

	/**
	 * @template T of object
	 * @param class-string<T> $target
	 * @return T
	 *
	 * @throws InvalidRequestException
	 */
	public function mapToObject(string $target, RequestContext $context): object
	{
		return $this->mapToType(TypeSchema::get()->mapper($target), $context, $target);
	}

	/**
	 * @template T
	 * @param Type<T> $type
	 * @param non-empty-string $targetType
	 * @return T
	 *
	 * @throws InvalidRequestException
	 */
	private function mapToType(Type $type, RequestContext $context, string $targetType): mixed
	{
		[$requestValues, $typeConfig, $keysToReindex] = $this->getRequestValuesAndTypeConfig($context);
		[$requestValues, $extraKeysError] = $this->mergeRequestValuesWithPresetValues($requestValues, $context->getPresetValues(), $typeConfig);

		$value = $this->typeSchemaProcessor->parse(
			$requestValues,
			$type,
			$typeConfig,
			collectErrors: true,
		);

		if ($value instanceof ErrorElement) {
			$reports = $value->getReports(config: $this->errorReportConfig);
			$this->checkForErrorsForPresetValues($reports, $context->getPresetValues(), $targetType);

			throw new InvalidRequestException($targetType, $this->createViolations($reports, $keysToReindex));
		} else if ($extraKeysError !== null) {
			throw new InvalidRequestException($targetType, $this->createViolations($extraKeysError->getReports(), $keysToReindex));
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
			defaultExtraKeysBehavior: $context->isExtraParameterAllowed() ? ExtraKeysBehavior::Ignore : ExtraKeysBehavior::Reject,
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
	 * @param iterable<ErrorReport> $reports
	 * @param array<non-empty-string, non-empty-string> $keysToReindex
	 * @return non-empty-list<Violation>
	 */
	private function createViolations(iterable $reports, array $keysToReindex = []): array
	{
		$grouped = [];
		$global = [];
		foreach ($reports as $report) {
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

	/**
	 * Checks if there are any errors related to preset values and throws a logic exception if there are, since preset values should be correct by definition.
	 * This is to catch any potential bugs in the implementation of the RequestMapper or the provided preset values.
	 * If there are errors related to preset values, it indicates a problem that needs to be addressed by the developers, rather than an issue with the client's request.
	 *
	 * @param array<ErrorReport> $reports
	 * @param array<non-empty-string, mixed> $presetValues
	 * @param non-empty-string $target
	 */
	private function checkForErrorsForPresetValues(array $reports, array $presetValues, string $target): void
	{
		if ($presetValues === []) {
			return;
		}

		$currentReports = [];
		foreach ($reports as $report) {
			if (count($report->path) !== 1) {
				continue;
			}

			$pathKey = $report->path[0];
			if (array_key_exists($pathKey->path, $presetValues)) {
				$currentReports[] = $report;
			}
		}

		if ($currentReports !== []) {
			$this->throwPresetValuesExceptionFromReports($currentReports, $target);
		}
	}

	/**
	 * @param non-empty-list<ErrorReport> $reports
	 * @param non-empty-string $target
	 */
	private function throwPresetValuesExceptionFromReports(array $reports, string $target): never
	{
		$messages = [];
		foreach ($reports as $report) {
			$messages[] = sprintf(
				'Field "%s": %s',
				implode('.', array_map(
					fn (Path $path): string => (string) $path->path,
					$report->path,
				)),
				$report->messageForDeveloper,
			);
		}

		throw new LogicException(sprintf(
			"Errors related to preset values found when mapping request to %s:\n%s",
			$target,
			implode("\n", $messages),
		));
	}

	/**
	 * @param mixed[] $requestValues
	 * @param array<non-empty-string, mixed> $presetValues
	 * @return array{ mixed[], ErrorElement|null }
	 */
	private function mergeRequestValuesWithPresetValues(array $requestValues, array $presetValues, TypeConfig $typeConfig): array
	{
		if (
			$presetValues === [] ||
			$typeConfig->defaultExtraKeysBehavior === ExtraKeysBehavior::Accept ||
			$typeConfig->defaultExtraKeysBehavior === ExtraKeysBehavior::Ignore
		) {
			return [$requestValues, null];
		}

		$extraKeys = [];

		foreach ($presetValues as $key => $value) {
			if (array_key_exists($key, $requestValues)) {
				$extraKeys[$key] = null;
			}

			$requestValues[$key] = $value;
		}

		if ($extraKeys !== []) {
			$errors = $this->typeSchemaProcessor->parse($extraKeys, TypeSchema::get()->arrayShape([])); // @phpstan-ignore argument.templateType
			if (!$errors instanceof ErrorElement) {
				throw new LogicException('Expected errors when merging preset values with request values, but got none.');
			}

			return [$requestValues, $errors];
		}

		return [$requestValues, null];
	}

}

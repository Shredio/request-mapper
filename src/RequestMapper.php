<?php declare(strict_types = 1);

namespace Shredio\RequestMapper;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use Shredio\Problem\Exception\ViolationAwareException;
use Shredio\Problem\Violation\FieldViolation;
use Shredio\RequestMapper\Attribute\RequestParam;
use Shredio\RequestMapper\Error\RequestMapperErrorCollector;
use Shredio\RequestMapper\Error\RequestMapperErrorFactory;
use Shredio\RequestMapper\Exception\InvalidPropertyException;
use Shredio\RequestMapper\Exception\LogicException;
use Shredio\RequestMapper\Exception\FieldNotExistsException;
use Shredio\RequestMapper\Field\Field;
use Shredio\RequestMapper\Filter\FilterVar;
use Shredio\RequestMapper\Mapper\ExternalPropertyMapper;
use Shredio\RequestMapper\Mapper\FilterVarPropertyMapper;
use Shredio\RequestMapper\Mapper\PropertyMapper;
use Shredio\RequestMapper\Mapper\RequestValueMapper;
use Shredio\RequestMapper\Request\Exception\InvalidRequestException;
use Shredio\RequestMapper\Request\RequestContext;
use Shredio\RequestMapper\Request\RequestLocation;
use Shredio\RequestMapper\Request\RequestValues;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Throwable;

final readonly class RequestMapper
{

	/**
	 * @param iterable<RequestValueMapper<object>> $valueMappers
	 */
	public function __construct(
		private RequestObjectMapper $requestObjectMapper,
		private ?RequestMediatorMapper $requestMediatorMapper = null,
		private ?ValidatorInterface $validator = null,
		#[AutowireIterator(tag: 'request_value_mapper')]
		private iterable $valueMappers = [],
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
	public function map(string $target, RequestContext $context): object
	{
		$mediator = $context->getMediatorClass();
		$mapToObjectContext = new RequestObjectMapperContext();
		$getViolations = RequestMapperErrorCollector::capture();

		try {
			$values = new RequestValues($context->getRequestValues(), $this->needToUseObjectMapper($context));
			$this->run($mediator ?? $target, $context, $values, $mapToObjectContext);
		} catch (Throwable $exception) { // @phpstan-ignore catch.neverThrown (unexpected exception can be thrown in value mappers, filters, etc)
			// cleanup errors collected during processing
			$getViolations();
			throw $exception;
		}

		$violations = $getViolations();
		if ($violations) {
			throw new InvalidRequestException($target, $violations);
		}

		if ($values->needToUseObjectMapper) {
			$output = $this->mapToObject($mediator ?? $target, $values->all(), $mapToObjectContext);
		} else {
			$output = $this->createOutput($target, $values);
		}

		$this->validateObject($target, $output);

		if ($mediator === null) {
			/** @var T */
			return $output;
		}

		if ($this->requestMediatorMapper === null) {
			throw new LogicException('RequestMediatorMapper is not set, but mediator class is defined in the context.');
		}

		return $this->requestMediatorMapper->map($output, $target);
	}

	/**
	 * @template T of object
	 * @param class-string<T> $className
	 * @param mixed[] $values
	 * @return T
	 *
	 * @throws InvalidRequestException
	 */
	private function mapToObject(string $className, array $values, RequestObjectMapperContext $context): object
	{
		return $this->requestObjectMapper->map($className, $values, $context);
	}

	/**
	 * @param class-string $targetClass
	 */
	private function run(
		string $targetClass,
		RequestContext $context,
		RequestValues $values,
		RequestObjectMapperContext $objectMapperContext,
	): void
	{
		$paramConfigs = $context->getParamConfigs();

		if (!$values->needToUseObjectMapper) {
			$this->typeless($targetClass, $context, $values, $paramConfigs, $objectMapperContext);
		} else {
			foreach ($paramConfigs as $targetKey => $paramConfig) {
				$this->paramConfig($targetClass, $targetKey, $paramConfig, $context, $values, $objectMapperContext);
			}
		}
	}

	/**
	 * @param class-string $targetClass
	 * @param array<string, RequestParam|RequestLocation> $paramConfigs
	 */
	private function typeless(
		string $targetClass,
		RequestContext $context,
		RequestValues $values,
		array $paramConfigs,
		RequestObjectMapperContext $objectMapperContext,
	): void
	{
		$reflectionClass = new ReflectionClass($targetClass);
		if ($reflectionClass->isAbstract()) {
			throw new LogicException(sprintf(
				'Class %s is abstract, but it is required for mapping.',
				$targetClass,
			));
		}

		$constructor = $reflectionClass->getConstructor();
		if ($constructor === null) {
			throw new LogicException(sprintf(
				'Class %s has no constructor, but it is required for mapping.',
				$targetClass,
			));
		}
		if (!$constructor->isPublic()) {
			throw new LogicException(sprintf(
				'Constructor of class %s is not public, but it is required for mapping.',
				$targetClass,
			));
		}

		foreach ($constructor->getParameters() as $parameter) {
			$parameterName = $parameter->getName();
			if (isset($paramConfigs[$parameterName])) {
				$this->paramConfig($targetClass, $parameterName, $paramConfigs[$parameterName], $context, $values, $objectMapperContext, $parameter);
				continue;
			}

			$reflectionType = $this->getTypeFromReflection($targetClass, $parameter);
			if (!$values->has($parameterName)) {
				$this->defaultValue($values, $parameter, $parameterName);
				continue;
			}

			$typeName = $this->stringifyReflectionType($reflectionType);
			$mapper = $this->getMapperByReflectionType($reflectionType);
			if ($mapper === null) {
				throw new LogicException(sprintf(
					'Parameter %s of class %s has unsupported type %s, use parameter configuration to specify behavior or add a custom value mapper.',
					$parameterName,
					$targetClass,
					$this->stringifyReflectionType($reflectionType),
				));
			}

			try {
				$sourceValue = $values->get($parameterName);
				$sourceValue = $mapper->tryToConvertToScalar($sourceValue, $typeName); // typeless
				$value = $mapper->convert($sourceValue, $typeName);
			} catch (InvalidPropertyException|ViolationAwareException $exception) {
				foreach ($exception->getViolations($parameterName) as $violation) {
					RequestMapperErrorCollector::addViolation($violation);
				}

				continue;
			}

			$values->set($parameterName, $value);
		}
	}

	/**
	 * @param class-string $targetClass
	 */
	private function paramConfig(
		string $targetClass,
		string $targetFieldName,
		RequestParam|RequestLocation $config,
		RequestContext $context,
		RequestValues $values,
		RequestObjectMapperContext $objectMapperContext,
		?ReflectionParameter $parameter = null,
	): bool
	{
		$paramConfig = $config instanceof RequestLocation ? new RequestParam(location: $config) : $config;
		$paramLocation = $paramConfig->location ?? $context->getDefaultRequestLocation();

		// fields
		$targetField = Field::create($targetFieldName);
		if ($paramConfig->sourceKey !== null) {
			$objectMapperContext->addParameterNameMapping($paramConfig->sourceKey, $targetFieldName);
			$sourceField = Field::create($context->normalizeKey($paramConfig->sourceKey, $paramLocation));
		} else {
			$sourceField = Field::create($context->normalizeKey($targetFieldName, $paramLocation));
		}

		// get value
		$fieldName = $sourceField->getFullPath();
		try {
			$sourceValue = $sourceField->getValueFrom($context->getRequestValuesByLocation($paramLocation));
		} catch (FieldNotExistsException) {
			if ($parameter === null) {
				return false;
			}

			return $this->defaultValue($values, $parameter, $targetField, $fieldName);
		}

		$sourceValue = $context->filterValue($sourceValue, $paramLocation);
		$filter = $paramConfig->getFilter();
		if ($filter !== null) {
			$mapper = new FilterVarPropertyMapper($filter);
			$typeName = $filter->getType();
		} else if ($parameter !== null) {
			$type = $this->getTypeFromReflection($targetClass, $parameter);
			$mapper = $this->getMapperByReflectionType($type);
			if ($mapper === null) {
				throw new LogicException(sprintf(
					'Parameter %s of class %s has unsupported type %s, use parameter configuration to specify behavior or add a custom value mapper.',
					$parameter->getName(),
					$targetClass,
					$this->stringifyReflectionType($type),
				));
			}

			$typeName = $this->stringifyReflectionType($type);
		} else {
			throw new LogicException(sprintf(
				'Parameter %s of class %s has unsupported type, use parameter configuration to specify behavior.',
				$sourceField->getFullPath(),
				$targetClass,
			));
		}

		if ($values->needToUseObjectMapper) {
			$values->set($targetField, $mapper->tryToConvertToScalar($sourceValue, $typeName));
			return true; // pass to object mapper
		}

		$isTypeStrict = $context->isTypeStrictByRequestLocation($paramLocation);
		if (!$isTypeStrict) {
			$sourceValue = $mapper->tryToConvertToScalar($sourceValue, $typeName); // typeless
		}

		try {
			$values->set($targetField, $mapper->convert($sourceValue, $typeName));
		} catch (InvalidPropertyException|ViolationAwareException $exception) {
			foreach ($exception->getViolations($sourceField->getFullPath()) as $violation) {
				RequestMapperErrorCollector::addViolation($violation);
			}

			return false;
		}

		return true;
	}

	private function defaultValue(RequestValues $values, ReflectionParameter $parameter, string|Field $target, ?string $source = null): bool
	{
		if ($parameter->isDefaultValueAvailable()) {
			$values->set($target, $parameter->getDefaultValue());

			return true;
		}

		RequestMapperErrorCollector::addViolation(RequestMapperErrorFactory::missingField($this->stringifyField($source ?? $target)));
		return false;
	}

	private function stringifyField(string|Field $field): string
	{
		return is_string($field) ? $field : $field->getFullPath();
	}

	/**
	 * @param class-string $targetClass
	 */
	private function getTypeFromReflection(string $targetClass, ReflectionParameter $parameter): ReflectionType
	{
		$parameterName = $parameter->getName();
		$type = $parameter->getType();
		if ($type === null) {
			throw new LogicException(sprintf(
				'Parameter %s of class %s has no type defined, add the type or use parameter configuration.',
				$parameterName,
				$targetClass,
			));
		}
		if (!$type instanceof ReflectionNamedType) {
			throw new LogicException(sprintf(
				'Parameter %s of class %s has a union or intersection type, these are not supported, use parameter configuration to specify behavior.',
				$parameterName,
				$targetClass,
			));
		}

		return $type;
	}

	private function getMapperByReflectionType(ReflectionType $type): ?PropertyMapper
	{
		$typeName = FilterVar::getValidTypeFromReflection($type);
		if ($typeName !== null) {
			return new FilterVarPropertyMapper(FilterVar::createFromType($typeName, $type->allowsNull()));
		}

		$typeName = $this->stringifyReflectionType($type);
		foreach ($this->valueMappers as $valueMapper) {
			if (!is_a($typeName, $valueMapper->getSupportedType(), true)) {
				continue;
			}

			return new ExternalPropertyMapper($valueMapper);
		}

		return null;
	}

	/**
	 * @template T of object
	 * @param class-string<T> $target
	 * @return T
	 */
	private function createOutput(string $target, RequestValues $values): object
	{
		$reflectionClass = new ReflectionClass($target);
		$constructor = $reflectionClass->getConstructor();
		if ($constructor === null) {
			throw new LogicException(sprintf(
				'Class %s has no constructor, but it is required for mapping.',
				$target,
			));
		}

		$arguments = [];
		foreach ($constructor->getParameters() as $parameter) {
			$arguments[$parameter->getName()] = $values->get($parameter->getName());
		}

		return new $target(...$arguments);
	}

	private function needToUseObjectMapper(RequestContext $context): bool
	{
		if (!$context->isTypeStrictByRequestLocation($context->getDefaultRequestLocation())) {
			return false;
		}

		foreach ($context->getParamConfigs() as $paramConfig) {
			if ($paramConfig instanceof RequestLocation) {
				if (!$context->isTypeStrictByRequestLocation($paramConfig)) {
					return false;
				}

				continue;
			}

			if ($paramConfig->location !== null && !$context->isTypeStrictByRequestLocation($paramConfig->location)) {
				return false;
			}
		}

		return true;
	}

	private function stringifyReflectionType(ReflectionType $reflectionType): string
	{
		if ($reflectionType instanceof ReflectionNamedType) {
			return $reflectionType->getName() . ($reflectionType->allowsNull() ? '|null' : '');
		}

		return (string) $reflectionType;
	}

	/**
	 * @throws InvalidRequestException
	 */
	private function validateObject(string $target, object $output): void
	{
		if ($this->validator === null) {
			return;
		}

		$list = $this->validator->validate($output);
		if ($list->count() === 0) {
			return;
		}

		$groupedMessages = [];
		foreach ($list as $violation) {
			$groupedMessages[$violation->getPropertyPath()][] = $violation->getMessage();
		}

		$violations = [];
		foreach ($groupedMessages as $fieldName => $messages) {
			$violations[] = new FieldViolation($fieldName, $messages);
		}

		throw new InvalidRequestException($target, $violations);
	}

}

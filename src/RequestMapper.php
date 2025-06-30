<?php declare(strict_types = 1);

namespace Shredio\RequestMapper;

use LogicException;
use ReflectionClass;
use ReflectionParameter;
use Shredio\Problem\Violation\FieldViolation;
use Shredio\Problem\Violation\GlobalViolation;
use Shredio\RequestMapper\Attribute\InAttribute;
use Shredio\RequestMapper\Attribute\InBody;
use Shredio\RequestMapper\Attribute\InHeader;
use Shredio\RequestMapper\Attribute\InPath;
use Shredio\RequestMapper\Attribute\InQuery;
use Shredio\RequestMapper\Attribute\InServer;
use Shredio\RequestMapper\Request\Exception\InvalidRequestException;
use Shredio\RequestMapper\Request\RequestContext;
use Shredio\RequestMapper\Request\RequestLocation;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class RequestMapper
{

	public function __construct(
		private DenormalizerInterface $denormalizer,
		private ?RequestMediatorMapper $requestMediatorMapper = null,
		private ?TranslatorInterface $translator = null,
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
		$requestValues = $context->getRequestValues();
		$values = $requestValues->values;
		$typeless = $requestValues->typeless;

		[$values, $typeless] = $this->processParameterAttributes($mediator ?? $target, $context, $values, $typeless);

		$output = $this->denormalize($values, $mediator ?? $target, $typeless, [
			AbstractNormalizer::REQUIRE_ALL_PROPERTIES => true,
			AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => $context->isExtraParametersAllowed(),
			DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS => true,
		]);

		assert(is_object($output));

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
	 * @throws InvalidRequestException
	 */
	public function mapSimpleType(string $type, bool $nullable, string $defaultPath, RequestContext $context): mixed
	{
		$requestValues = $context->getRequestValues();
		$values = $requestValues->values;
		$path = $context->getPath() ?? $defaultPath;

		if (!array_key_exists($path, $values)) {
			if ($nullable) {
				return null;
			}

			throw new InvalidRequestException($type, [
				new FieldViolation($path, [$this->getMissingFieldMessage()]),
			]);
		}

		$value = $values[$path];

		if ($value === null) {
			if ($nullable) {
				return null;
			}

			throw new InvalidRequestException($type, [
				new FieldViolation($path, [$this->getMissingFieldMessage()]),
			]);
		}

		return $this->denormalize($value, $type, $requestValues->typeless);
	}

	/**
	 * @param mixed[] $context
	 * @throws InvalidRequestException
	 */
	private function denormalize(mixed $value, string $type, bool $typeless, array $context = []): mixed
	{
		try {
			return $this->denormalizer->denormalize($value, $type, $typeless ? 'csv' : null, $context);
		} catch (ExceptionInterface $exception) {
			$violations = $this->extractViolationsFromException($exception);

			if ($violations !== []) {
				throw new InvalidRequestException($type, $violations, $exception);
			}

			throw $exception;
		}
	}

	private function getInvalidTypeMessage(string $type): string
	{
		return $this->translate('This value should be of type {{ type }}.', ['{{ type }}' => $type]);
	}

	private function getMissingFieldMessage(): string
	{
		return $this->translate('This field is missing.');
	}

	private function getExtraFieldMessage(): string
	{
		return $this->translate('This field is not allowed.');
	}

	/**
	 * @param array<string, string> $parameters
	 */
	private function translate(string $message, array $parameters = []): string
	{
		if ($this->translator !== null) {
			return $this->translator->trans($message, $parameters);
		}

		return strtr($message, $parameters);
	}

	/**
	 * @return list<FieldViolation|GlobalViolation>
	 */
	private function extractViolationsFromException(ExceptionInterface $exception): array
	{
		$violations = [];

		if ($exception instanceof ExtraAttributesException) {
			/** @var string|int $attribute */
			foreach ($exception->getExtraAttributes() as $attribute) {
				$violations[] = new FieldViolation((string) $attribute, [$this->getExtraFieldMessage()]);
			}
		} else if ($exception instanceof MissingConstructorArgumentsException) {
			foreach ($exception->getMissingConstructorArguments() as $argument) {
				$violations[] = new FieldViolation($argument, [$this->getMissingFieldMessage()]);
			}
		} else if ($exception instanceof PartialDenormalizationException) {
			foreach ($exception->getErrors() as $error) {
				$originalMessage = $error->getMessage();
				$path = $error->getPath();

				if (str_contains($originalMessage, 'because the class misses the')) {
					// This is a special case for missing constructor arguments
					$messages = [$this->getMissingFieldMessage()];
				} else {
					$type = implode('|', $error->getExpectedTypes() ?? ['?']);
					$messages = [$this->getInvalidTypeMessage($type)];
				}

				if ($path === null) {
					$violations[] = new GlobalViolation($messages);
				} else {
					$violations[] = new FieldViolation($path, $messages);
				}
			}
		}

		return $violations;
	}

	/**
	 * @param class-string $target
	 * @param mixed[] $values
	 * @return array{mixed[], bool}
	 */
	private function processParameterAttributes(string $target, RequestContext $context, array $values, bool $typeless): array
	{
		$reflection = new ReflectionClass($target);
		$constructor = $reflection->getConstructor();
		$defaultLocation = $context->getDefaultRequestLocation();

		if ($constructor === null) {
			return [$values, $typeless];
		}

		foreach ($constructor->getParameters() as $parameter) {
			$locationInfo = $this->getParameterLocationInfo($parameter);

			if ($locationInfo === null) {
				$location = $defaultLocation;
				$sourceKey = null;
			} else {
				[$location, $sourceKey] = $locationInfo;
			}

			$requestValues = $context->getRequestValuesByLocation($location);
			$key = $sourceKey ?? $parameter->getName();

			if ($requestValues->typeless) {
				$typeless = true;
			}

			if ($requestValues->hasKey($key)) {
				$values[$parameter->getName()] = $requestValues->getValue($key);
			} else {
				unset($values[$parameter->getName()]); // override the value and eventually exception will be thrown later
			}
		}

		return [$values, $typeless];
	}

	/**
	 * @return array{RequestLocation, string|null}|null
	 */
	private function getParameterLocationInfo(ReflectionParameter $parameter): ?array
	{
		$attributes = $parameter->getAttributes();

		foreach ($attributes as $attribute) {
			$instance = $attribute->newInstance();

			$result = match (true) {
				$instance instanceof InPath => [RequestLocation::Path, $instance->name],
				$instance instanceof InQuery => [RequestLocation::Query, $instance->name],
				$instance instanceof InBody => [RequestLocation::Body, $instance->name],
				$instance instanceof InHeader => [RequestLocation::Header, $instance->name],
				$instance instanceof InAttribute => [RequestLocation::Attribute, $instance->name],
				$instance instanceof InServer => [RequestLocation::Server, $instance->name],
				default => null,
			};

			if ($result !== null) {
				return $result;
			}
		}

		return null;
	}

}

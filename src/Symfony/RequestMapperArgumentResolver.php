<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Symfony;

use LogicException;
use Shredio\Problem\Violation\GlobalViolation;
use Shredio\RequestMapper\Attribute\MapFromRequest;
use Shredio\RequestMapper\Attribute\RequestParam;
use Shredio\RequestMapper\Attribute\StringBodyFromRequest;
use Shredio\RequestMapper\Request\Exception\InvalidRequestException;
use Shredio\RequestMapper\RequestParameterMapper;
use Shredio\TypeSchema\Exception\UnsupportedTypeException;
use Shredio\TypeSchema\Helper\TypeSchemaHelper;
use Shredio\TypeSchema\TypeSchema;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class RequestMapperArgumentResolver implements EventSubscriberInterface, ValueResolverInterface
{

	public function __construct(
		private RequestParameterMapper $requestMapper,
	)
	{
	}

	public static function getSubscribedEvents(): array
	{
		return [
			KernelEvents::CONTROLLER_ARGUMENTS => [
				['onKernelControllerArguments', 100],
			],
		];
	}

	/**
	 * @return list<ResolveMapFromRequest>
	 */
	public function resolve(Request $request, ArgumentMetadata $argument): iterable
	{
		foreach ($argument->getAttributes() as $attribute) {
			if (in_array($attribute::class, [StringBodyFromRequest::class, MapFromRequest::class, RequestParam::class], true)) {
				return [new ResolveMapFromRequest($attribute, $argument)];
			}
		}

		return [];
	}

	/**
	 * @throws InvalidRequestException
	 */
	private function processBodyContent(
		Request $request,
		ArgumentMetadata $argument,
		StringBodyFromRequest $attribute,
	): ?string
	{
		$content = $request->getContent();

		if ($attribute->trim) {
			$content = trim($content);
		}

		if ($content === '' && !$argument->isNullable()) {
			throw new InvalidRequestException('body string', [
				new GlobalViolation(['Missing HTTP body']),
			]);
		}

		if ($content === '' && $argument->isNullable()) {
			return null;
		}

		return $content;
	}

	/**
	 * @throws InvalidRequestException
	 */
	private function processMapFromRequest(
		Request $request,
		ArgumentMetadata $argument,
		MapFromRequest $attribute,
	): mixed
	{
		$type = $argument->getType();
		if (!$type) {
			throw new LogicException(\sprintf('Could not resolve the "$%s" controller argument: argument should be typed.', $argument->getName()));
		}

		$context = SymfonyRequestContextFactory::createFrom($request, $attribute->createConfiguration());

		if (class_exists($type) && !enum_exists($type)) {
			return $this->requestMapper->mapToObject($type, $context);
		} else {
			throw new LogicException(sprintf('Could not resolve the "$%s" controller argument: type "%s" is not a class.', $argument->getName(), $type));
		}
	}

	/**
	 * @throws InvalidRequestException
	 */
	private function processRequestParam(
		Request $request,
		ArgumentMetadata $argument,
		RequestParam $attribute,
	): mixed
	{
		/** @var class-string $controllerName */
		$controllerName = $argument->getControllerName();

		$type = $argument->getType();
		if ($type === null) {
			throw new LogicException(\sprintf('Could not resolve the "$%s" controller argument: argument should be typed.', $argument->getName()));
		}

		try {
			$type = TypeSchemaHelper::fromStringType($type, $argument->isNullable());
		} catch (UnsupportedTypeException) {
			throw new LogicException(\sprintf('Could not resolve the "$%s" controller argument: unsupported type "%s".', $argument->getName(), $argument->getType()));
		}

		$ts = TypeSchema::get();
		if ($argument->isNullable()) {
			$type = $ts->nullable($type);
		}

		if ($argument->hasDefaultValue()) {
			$type = $ts->optional($type);
		}

		$context = SymfonyRequestContextFactory::createFrom($request, $attribute->createConfiguration());

		$sourceKey = $attribute->sourceKey ?? $argument->getName();
		$isOptional = $argument->hasDefaultValue();

		$values = $this->requestMapper->mapToArray($ts->arrayShape([
			$sourceKey => $type,
		]), $context);

		if (!array_key_exists($sourceKey, $values)) {
			if ($isOptional) {
				return $argument->getDefaultValue();
			}

			throw new \Shredio\RequestMapper\Exception\LogicException(
				sprintf('Could not resolve the "$%s" controller argument: missing required request parameter "%s".', $argument->getName(), $sourceKey),
			);
		}

		return $values[$sourceKey];
	}

	/**
	 * @throws InvalidRequestException
	 */
	public function onKernelControllerArguments(ControllerArgumentsEvent $event): void
	{
		$request = $event->getRequest();
		$arguments = $event->getArguments();
		$exceptions = [];

		foreach ($arguments as $i => $argument) {
			if (!$argument instanceof ResolveMapFromRequest) {
				continue;
			}

			try {
				if ($argument->attribute instanceof MapFromRequest) {
					$arguments[$i] = $this->processMapFromRequest($request, $argument->metadata, $argument->attribute);
				} elseif ($argument->attribute instanceof RequestParam) {
					$arguments[$i] = $this->processRequestParam($request, $argument->metadata, $argument->attribute);
				} else {
					$arguments[$i] = $this->processBodyContent($request, $argument->metadata, $argument->attribute);
				}
			} catch (InvalidRequestException $exception) {
				$exceptions[] = $exception;
			}
		}

		if (count($exceptions) > 0) {
			throw InvalidRequestException::merge($exceptions);
		}

		$event->setArguments($arguments);
	}

}

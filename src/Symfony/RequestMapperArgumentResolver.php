<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Symfony;

use InvalidArgumentException;
use LogicException;
use Shredio\Problem\Violation\GlobalViolation;
use Shredio\RequestMapper\Attribute\MapFromRequest;
use Shredio\RequestMapper\Attribute\RequestParam;
use Shredio\RequestMapper\Attribute\StringBodyFromRequest;
use Shredio\RequestMapper\Error\RequestMapperErrorFactory;
use Shredio\RequestMapper\Exception\FieldNotExistsException;
use Shredio\RequestMapper\Field\Field;
use Shredio\RequestMapper\Filter\FilterVar;
use Shredio\RequestMapper\Request\Exception\InvalidRequestException;
use Shredio\RequestMapper\Request\SymfonyRequestContext;
use Shredio\RequestMapper\RequestMapper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class RequestMapperArgumentResolver implements EventSubscriberInterface, ValueResolverInterface
{

	public function __construct(
		private RequestMapper $requestMapper,
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

		$context = new SymfonyRequestContext(
			$request,
			$attribute->configuration,
			$attribute->location,
			$attribute->mediator,
			$attribute->path,
		);

		if (class_exists($type) && !enum_exists($type)) {
			return $this->requestMapper->map($type, $context);
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
		$location = $attribute->location ?? SymfonyRequestContext::determineDefaultRequestLocation($request);
		$field = Field::create($attribute->sourceKey ?? $argument->getName());

		try {
			$value = $field->getValueFrom(SymfonyRequestContext::getValuesFromRequest($request, $location));
		} catch (FieldNotExistsException) {
			if ($argument->isNullable()) {
				return null;
			}

			if ($argument->hasDefaultValue()) {
				return $argument->getDefaultValue();
			}

			throw new InvalidRequestException(
				'requestParam',
				[RequestMapperErrorFactory::missingField($field->getFullPath())],
			);
		}

		$filter = $attribute->getFilter();
		if ($filter === null) {
			$parameterType = $argument->getType() ?? 'mixed';
			try {
				$filter = FilterVar::createFromType($parameterType, $argument->isNullable());
			} catch (InvalidArgumentException) {
				throw new LogicException(
					sprintf(
						'Could not resolve the "$%s" controller argument: unsupported type "%s".',
						$argument->getName(),
						$parameterType,
					),
				);
			}
		}

		$result = $filter->call($value);
		if (!$result->isValid) {
			throw new InvalidRequestException(
				'requestParam',
				[RequestMapperErrorFactory::invalidTypeMessage($field->getFullPath(), $filter->getNullableType())],
			);
		}

		return $result->value;
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

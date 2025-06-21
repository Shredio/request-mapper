<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Symfony;

use LogicException;
use Shredio\RequestMapper\Attribute\MapFromRequest;
use Shredio\RequestMapper\Request\Exception\InvalidRequestException;
use Shredio\RequestMapper\Request\SymfonyRequestContext;
use Shredio\RequestMapper\RequestMapper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class MapFromRequestControllerArgumentResolver implements EventSubscriberInterface, ValueResolverInterface
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
		$attribute = $argument->getAttributesOfType(MapFromRequest::class)[0] ?? null;

		if (!$attribute) {
			return [];
		}

		return [new ResolveMapFromRequest($attribute, $argument)];
	}

	/**
	 * @throws InvalidRequestException
	 */
	public function onKernelControllerArguments(ControllerArgumentsEvent $event): void
	{
		$request = $event->getRequest();
		$arguments = $event->getArguments();

		foreach ($arguments as $i => $argument) {
			if (!$argument instanceof ResolveMapFromRequest) {
				continue;
			}

			$metadata = $argument->metadata;
			$attribute = $argument->attribute;

			$type = $metadata->getType();
			if (!$type) {
				throw new LogicException(\sprintf('Could not resolve the "$%s" controller argument: argument should be typed.', $metadata->getName()));
			}

			$context = new SymfonyRequestContext(
				$request,
				$attribute->location,
				$attribute->mediator,
				$attribute->path,
			);

			if (class_exists($type) && !enum_exists($type)) {
				$resolved = $this->requestMapper->map($type, $context);
			} else {
				$resolved = $this->requestMapper->mapSimpleType($type, $metadata->isNullable(), $metadata->getName(), $context);
			}

			$arguments[$i] = $resolved;
		}

		$event->setArguments($arguments);
	}

}

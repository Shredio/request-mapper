<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Symfony;

use Shredio\RequestMapper\Exception\LogicException;
use Shredio\RequestMapper\Request\DefaultRequestContext;
use Shredio\RequestMapper\Request\RequestContext;
use Shredio\RequestMapper\Request\RequestContextFactory;
use Shredio\RequestMapper\Request\RequestLocation;
use Shredio\RequestMapper\RequestMapperConfiguration;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class SymfonyRequestContextFactory implements RequestContextFactory
{

	public function __construct(
		private RequestStack $requestStack,
	)
	{
	}

	public function create(?RequestMapperConfiguration $configuration = null): RequestContext
	{
		$currentRequest = $this->requestStack->getCurrentRequest();
		if ($currentRequest === null) {
			throw new LogicException('No current request found in RequestStack.');
		}

		return self::createFrom($currentRequest, $configuration);
	}

	public static function createFrom(
		Request $currentRequest,
		?RequestMapperConfiguration $configuration = null,
	): RequestContext
	{
		return new DefaultRequestContext(
			new SymfonyRequestValueProvider($currentRequest),
			new SymfonyRequestValueNormalizer(),
			new SymfonyRequestKeyNormalizer(),
			$configuration->parameters ?? [],
			$configuration->location ?? self::getDefaultRequestLocation($currentRequest),
			$configuration->presetValues ?? [],
		);
	}

	private static function getDefaultRequestLocation(Request $request): RequestLocation
	{
		return match ($request->getMethod()) {
			'POST', 'PATCH', 'PUT' => RequestLocation::Body,
			default => RequestLocation::Query,
		};
	}

}

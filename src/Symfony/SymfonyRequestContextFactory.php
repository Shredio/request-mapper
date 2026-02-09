<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Symfony;

use Shredio\RequestMapper\Attribute\RequestParam;
use Shredio\RequestMapper\Exception\LogicException;
use Shredio\RequestMapper\Request\DefaultRequestContext;
use Shredio\RequestMapper\Request\RequestContext;
use Shredio\RequestMapper\Request\RequestContextFactory;
use Shredio\RequestMapper\Request\RequestLocation;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class SymfonyRequestContextFactory implements RequestContextFactory
{

	public function __construct(
		private RequestStack $requestStack,
	)
	{
	}

	public function create(array $paramConfig = [], ?RequestLocation $location = null, array $staticValues = []): RequestContext
	{
		$currentRequest = $this->requestStack->getCurrentRequest();
		if ($currentRequest === null) {
			throw new LogicException('No current request found in RequestStack.');
		}

		return self::createFrom($currentRequest, $paramConfig, $location, $staticValues);
	}

	/**
	 * @param array<non-empty-string, RequestParam|RequestLocation> $paramConfig
	 * @param array<non-empty-string, mixed> $staticValues
	 */
	public static function createFrom(
		Request $currentRequest,
		array $paramConfig = [],
		?RequestLocation $location = null,
		array $staticValues = [],
	): RequestContext
	{
		return new DefaultRequestContext(
			new SymfonyRequestValueProvider($currentRequest),
			new SymfonyRequestValueNormalizer(),
			new SymfonyRequestKeyNormalizer(),
			$paramConfig,
			$location ?? self::getDefaultRequestLocation($currentRequest),
			$staticValues,
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

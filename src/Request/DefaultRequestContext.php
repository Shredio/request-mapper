<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Request;

use Shredio\RequestMapper\Attribute\RequestParam;

final readonly class DefaultRequestContext implements RequestContext
{

	/**
	 * @param array<non-empty-string, RequestParam|RequestLocation> $paramConfig
	 */
	public function __construct(
		private RequestValueProvider $valueProvider,
		private RequestValueNormalizer $valueNormalizer,
		private RequestKeyNormalizer $keyNormalizer,
		private array $paramConfig = [],
		private RequestLocation $location = RequestLocation::Query,
	)
	{
	}

	/**
	 * @return array<non-empty-string, RequestParam|RequestLocation>
	 */
	public function getParamConfigs(): array
	{
		return $this->paramConfig;
	}

	public function getDefaultRequestLocation(): RequestLocation
	{
		return $this->location;
	}

	public function getRequestValues(): array
	{
		return $this->valueProvider->getValues($this->location);
	}

	public function getRequestValuesByLocation(RequestLocation $location): array
	{
		return $this->valueProvider->getValues($location);
	}

	public function normalizeKey(string $key, RequestLocation $location): string
	{
		return $this->keyNormalizer->normalize($key, $location);
	}

	public function filterValue(mixed $value, RequestLocation $location): mixed
	{
		return $this->valueNormalizer->normalize($value, $location);
	}

}

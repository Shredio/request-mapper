<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Request;

use Shredio\RequestMapper\Attribute\RequestParam;
use Symfony\Component\HttpFoundation\Request;

final readonly class SymfonyRequestContext implements RequestContext
{

	private RequestLocation $location;

	/**
	 * @param array<non-empty-string, RequestParam|RequestLocation> $paramConfig
	 */
	public function __construct(
		private Request $request,
		private array $paramConfig = [],
		?RequestLocation $location = null,
		private ?string $path = null,
	)
	{
		$this->location = $location ?? self::determineDefaultRequestLocation($this->request);
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

	public function isTypeStrictByRequestLocation(RequestLocation $location): bool
	{
		return $location === RequestLocation::Body;
	}

	public function getRequestValues(): array
	{
		return $this->getRequestValuesByLocation($this->getDefaultRequestLocation());
	}

	public function getRequestValuesByLocation(RequestLocation $location): array
	{
		return self::getValuesFromRequest($this->request, $location);
	}

	/**
	 * @return mixed[]
	 */
	public static function getValuesFromRequest(Request $request, RequestLocation $location): array
	{
		return match ($location) {
			RequestLocation::Body => $request->getPayload()->all(),
			RequestLocation::Attribute, RequestLocation::Route => $request->attributes->all(),
			RequestLocation::Query => $request->query->all(),
			RequestLocation::Header => $request->headers->all(),
			RequestLocation::Server => $request->server->all(),
		};
	}

	public static function determineDefaultRequestLocation(Request $request): RequestLocation
	{
		return match ($request->getMethod()) {
			'POST', 'PATCH', 'PUT' => RequestLocation::Body,
			default => RequestLocation::Query,
		};
	}

	public function normalizeKey(string $key, RequestLocation $location): string
	{
		if ($location === RequestLocation::Header) {
			return strtolower($key);
		}

		return $key;
	}

	public function filterValue(mixed $value, RequestLocation $location): mixed
	{
		if ($location === RequestLocation::Header && is_array($value)) {
			// Symfony returns headers as array, but we want single value
			return implode(', ', $value);
		}

		return $value;
	}

	public function getPath(): ?string
	{
		return $this->path;
	}

	public function isExtraParametersAllowed(): bool
	{
		if ($this->getDefaultRequestLocation() === RequestLocation::Body) {
			return false;
		}

		return true;
	}

}

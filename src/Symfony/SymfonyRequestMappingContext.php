<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Symfony;

use Shredio\RequestMapper\Context\RequestMappingContext;
use Shredio\RequestMapper\Enum\RequestLocation;
use Shredio\RequestMapper\Parameter\RequestParameter;
use Symfony\Component\HttpFoundation\Request;

final readonly class SymfonyRequestMappingContext implements RequestMappingContext
{

	/**
	 * @param list<RequestParameter> $parameters
	 * @param class-string|null $mediatorClass
	 */
	public function __construct(
		private Request $request,
		private array $parameters = [],
		private ?RequestLocation $location = null,
		private ?string $mediatorClass = null,
	)
	{
	}

	public function getLocation(): RequestLocation
	{
		if ($this->location) {
			return $this->location;
		}

		return match ($this->request->getMethod()) {
			'POST', 'PATCH', 'PUT' => RequestLocation::Body,
			default => RequestLocation::Query,
		};
	}

	public function getRequestParameters(): array
	{
		return $this->parameters;
	}

	public function getMediatorClass(): ?string
	{
		return $this->mediatorClass;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getRequestValues(): array
	{
		return $this->getRequestValuesByLocation($this->getLocation());
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getRequestValuesByLocation(RequestLocation $location): array
	{
		return match ($location) {
			RequestLocation::Query => $this->getQuery(),
			RequestLocation::Body => $this->getBody(),
			RequestLocation::Header => $this->getHeaders(),
			RequestLocation::Attribute => $this->getAttributes(),
			RequestLocation::Server => $this->getServer(),
		};
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getQuery(): array
	{
		return $this->request->query->all();
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getHeaders(): array
	{
		$headers = [];

		foreach ($this->request->headers->all() as $key => $value) {
			if (isset($value[0])) {
				$headers[$key] = $value[0];
			}
		}

		return $headers;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getBody(): array
	{
		return $this->request->request->all();
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getAttributes(): array
	{
		return $this->request->attributes->all();
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getServer(): array
	{
		return $this->request->server->all();
	}

}

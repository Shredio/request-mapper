<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Request;

use Symfony\Component\HttpFoundation\Request;

final readonly class SymfonyRequestContext implements RequestContext
{

	/**
	 * @param class-string|null $mediatorClass
	 */
	public function __construct(
		private Request $request,
		private ?RequestLocation $location = null,
		private ?string $mediatorClass = null,
		private ?string $path = null,
	)
	{
	}

	public function getMediatorClass(): ?string
	{
		return $this->mediatorClass;
	}

	public function getDefaultRequestLocation(): RequestLocation
	{
		if ($this->location) {
			return $this->location;
		}

		return match ($this->request->getMethod()) {
			'POST', 'PATCH', 'PUT' => RequestLocation::Body,
			default => RequestLocation::Query,
		};
	}

	public function getRequestValues(): RequestValues
	{
		return $this->getRequestValuesByLocation($this->getDefaultRequestLocation());
	}

	public function getRequestValuesByLocation(RequestLocation $location): RequestValues
	{
		return match ($location) {
			RequestLocation::Body => new RequestValues($this->request->getPayload()->all()),
			RequestLocation::Attribute, RequestLocation::Path => new RequestValues($this->request->attributes->all()),
			RequestLocation::Query => new RequestValues($this->request->query->all(), true),
			RequestLocation::Header => new RequestHeaderValues($this->request->headers->all(), true),
			RequestLocation::Server => new RequestValues($this->request->server->all()),
		};
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

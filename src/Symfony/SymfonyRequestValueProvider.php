<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Symfony;

use Shredio\RequestMapper\Request\RequestLocation;
use Shredio\RequestMapper\Request\RequestValueProvider;
use Symfony\Component\HttpFoundation\Request;

final readonly class SymfonyRequestValueProvider implements RequestValueProvider
{

	public function __construct(
		private Request $request,
	)
	{
	}

	public function getValues(RequestLocation $location): array
	{
		return match ($location) {
			RequestLocation::Body => $this->request->getPayload()->all(),
			RequestLocation::Attribute, RequestLocation::Route => $this->request->attributes->all(),
			RequestLocation::Query => $this->request->query->all(),
			RequestLocation::Header => $this->request->headers->all(),
			RequestLocation::Server => $this->request->server->all(),
		};
	}

}

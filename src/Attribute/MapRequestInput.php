<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Attribute;

use Attribute;
use Shredio\RequestMapper\Enum\RequestLocation;
use Shredio\RequestMapper\Parameter\RequestParameter;

#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class MapRequestInput
{

	/**
	 * @param list<RequestParameter> $parameters
	 * @param class-string|null $mediator
	 */
	public function __construct(
		public array $parameters = [],
		public ?RequestLocation $location = null,
		public ?string $mediator = null,
	)
	{
	}

}

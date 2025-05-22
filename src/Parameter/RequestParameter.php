<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Parameter;

use Shredio\RequestMapper\Enum\RequestLocation;

final readonly class RequestParameter
{

	public function __construct(
		public string $property,
		public ?RequestLocation $location = null,
		public ?string $sourceKey = null,
	)
	{
	}

}

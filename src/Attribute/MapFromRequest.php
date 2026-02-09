<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Attribute;

use Attribute;
use Shredio\RequestMapper\Request\RequestLocation;

/**
 * Maps data from HTTP request to object parameters.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class MapFromRequest
{

	/**
	 * @param array<non-empty-string, RequestParam|RequestLocation> $configuration
	 */
	public function __construct(
		public array $configuration = [],
		public ?RequestLocation $location = null,
	)
	{
	}

}

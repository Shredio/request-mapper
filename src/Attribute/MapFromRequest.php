<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Attribute;

use Attribute;
use Shredio\RequestMapper\Request\RequestLocation;

/**
 * Maps data from HTTP request to object parameters.
 *
 * You can now use location attributes directly on constructor parameters:
 * - #[InPath] for path parameters
 * - #[InQuery] for query parameters  
 * - #[InBody] for request body parameters
 * - #[InHeader] for header parameters
 * - #[InAttribute] for request attributes
 * - #[InServer] for server variables
 *
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class MapFromRequest
{

	/**
	 * @param class-string|null $mediator
	 */
	public function __construct(
		public ?RequestLocation $location = null,
		public ?string $mediator = null,
		public ?string $path = null,
		public ?bool $typeStrict = null,
	)
	{
	}

}

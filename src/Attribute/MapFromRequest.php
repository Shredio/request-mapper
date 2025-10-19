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
	 * @param non-empty-string|null $path Dot notation path to the value in the request (e.g. "user.email")
	 */
	public function __construct(
		public array $configuration = [],
		public ?RequestLocation $location = null,
		public ?string $path = null,
	)
	{
		if ($this->path !== null && str_contains($this->path, '.')) {
			throw new \InvalidArgumentException('Dot notation is not supported in MapFromRequest attribute path.');
		}
	}

}

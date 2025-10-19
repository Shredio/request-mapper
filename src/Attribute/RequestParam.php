<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Attribute;

use Attribute;
use Shredio\RequestMapper\Request\RequestLocation;

#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class RequestParam
{

	/**
	 * @param non-empty-string|null $sourceKey
	 */
	public function __construct(
		public ?string $sourceKey = null,
		public ?RequestLocation $location = null,
	)
	{
	}


}

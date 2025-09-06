<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Symfony;

use Shredio\RequestMapper\Attribute\MapFromRequest;
use Shredio\RequestMapper\Attribute\RequestParam;
use Shredio\RequestMapper\Attribute\StringBodyFromRequest;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * @internal
 */
final readonly class ResolveMapFromRequest
{

	public function __construct(
		public MapFromRequest|RequestParam|StringBodyFromRequest $attribute,
		public ArgumentMetadata $metadata,
	)
	{
	}

}

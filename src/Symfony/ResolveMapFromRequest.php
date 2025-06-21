<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Symfony;

use Shredio\RequestMapper\Attribute\MapFromRequest;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * @internal
 */
final readonly class ResolveMapFromRequest
{

	public function __construct(
		public MapFromRequest $attribute,
		public ArgumentMetadata $metadata,
	)
	{
	}

}

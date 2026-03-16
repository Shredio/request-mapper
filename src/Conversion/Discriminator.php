<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Conversion;

use Shredio\RequestMapper\Request\RequestLocation;

final readonly class Discriminator
{

	/**
	 * @param non-empty-string $key
	 * @param non-empty-array<array-key, class-string> $mapping
	 */
	public function __construct(
		public string $key,
		public array $mapping,
		public ?RequestLocation $location = null,
	)
	{
	}

}

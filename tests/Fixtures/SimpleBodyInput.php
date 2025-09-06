<?php declare(strict_types = 1);

namespace Tests\Fixtures;

use ShipMonk\InputMapper\Compiler\Mapper\Optional;

final readonly class SimpleBodyInput
{

	public function __construct(
		public string $title,
		#[Optional(default: 'default content')]
		public string $content = 'default content',
	)
	{
	}

}

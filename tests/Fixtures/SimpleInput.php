<?php declare(strict_types = 1);

namespace Tests\Fixtures;

use ShipMonk\InputMapper\Compiler\Mapper\Optional;

final readonly class SimpleInput
{

	public function __construct(
		public string $name,
		#[Optional(default: 25)]
		public int $age = 25,
	)
	{
	}

}

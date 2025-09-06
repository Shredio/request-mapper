<?php declare(strict_types = 1);

namespace Tests\Fixtures;

use ShipMonk\InputMapper\Compiler\Mapper\Optional;

final readonly class UserInput
{

	public function __construct(
		public int $id,
		#[Optional(default: '')]
		public string $filter = '',
		#[Optional(default: '')]
		public string $apiKey = '',
	)
	{
	}

}

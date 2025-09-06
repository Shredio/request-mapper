<?php declare(strict_types = 1);

namespace Tests\Fixtures;

use ShipMonk\InputMapper\Compiler\Mapper\Optional;

final readonly class ComplexInput
{

	public function __construct(
		public int $pathId,
		public string $queryParam,
		public string $bodyContent,
		public string $headerValue,
		public string $attributeValue,
		public string $serverHost,
		#[Optional(default: 'default')]
		public string $customPath = 'default',
		#[Optional(default: 10)]
		public int $customQuery = 10,
	)
	{
	}

}

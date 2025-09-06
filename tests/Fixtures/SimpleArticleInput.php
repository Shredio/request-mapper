<?php declare(strict_types = 1);

namespace Tests\Fixtures;

use ShipMonk\InputMapper\Compiler\Mapper\Optional;

final readonly class SimpleArticleInput
{

	public function __construct(
		public int $id,
		#[Optional(default: 'general')]
		public string $category = 'general',
		#[Optional(default: true)]
		public bool $published = true,
	)
	{
	}

}

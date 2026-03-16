<?php declare(strict_types = 1);

namespace Tests\Fixtures;

final readonly class DogInput
{

	public function __construct(
		public string $name,
		public string $breed = 'mixed',
	)
	{
	}

}

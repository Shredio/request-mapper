<?php declare(strict_types = 1);

namespace Tests\Fixtures;

final readonly class SimpleInput
{

	public function __construct(
		public string $name,
		public int $age = 25,
	)
	{
	}

}
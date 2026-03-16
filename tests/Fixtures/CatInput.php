<?php declare(strict_types = 1);

namespace Tests\Fixtures;

final readonly class CatInput
{

	public function __construct(
		public string $name,
		public bool $indoor = true,
	)
	{
	}

}

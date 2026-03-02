<?php declare(strict_types = 1);

namespace Tests\Fixtures;

final readonly class ArrayInput
{

	/**
	 * @param list<non-empty-string> $names
	 */
	public function __construct(
		public array $names,
	)
	{
	}

}

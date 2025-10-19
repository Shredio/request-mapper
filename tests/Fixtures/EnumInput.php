<?php declare(strict_types = 1);

namespace Tests\Fixtures;

class EnumInput
{

	public function __construct(
		public Status $status,
	)
	{
	}

}

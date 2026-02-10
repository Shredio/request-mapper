<?php declare(strict_types = 1);

namespace Tests\Fixtures;

class IntBackedEnumInput
{

	public function __construct(
		public IntBackedStatus $status,
	)
	{
	}

}

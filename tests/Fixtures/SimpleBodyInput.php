<?php declare(strict_types = 1);

namespace Tests\Fixtures;

use Shredio\RequestMapper\Attribute\InBody;

final readonly class SimpleBodyInput
{

	public function __construct(
		#[InBody] public string $title,
		#[InBody] public string $content = 'default content',
	)
	{
	}

}
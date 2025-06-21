<?php declare(strict_types = 1);

namespace Tests\Fixtures;

use Shredio\RequestMapper\Attribute\InBody;
use Shredio\RequestMapper\Attribute\InPath;

final readonly class BodyOnlyInput
{

	public function __construct(
		#[InPath] public int $id,
		#[InBody] public string $title,
		#[InBody] public string $content,
		#[InBody] public string $category = 'general',
		#[InBody] public bool $published = true,
	)
	{
	}

}
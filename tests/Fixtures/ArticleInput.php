<?php declare(strict_types = 1);

namespace Tests\Fixtures;

use Shredio\RequestMapper\Attribute\InBody;
use Shredio\RequestMapper\Attribute\InPath;
use Shredio\RequestMapper\Attribute\InQuery;

final readonly class ArticleInput
{

	public function __construct(
		#[InPath] public int $id,
		#[InQuery] public string $category = 'general',
		#[InQuery] public bool $published = true,
		#[InBody] public string $title,
		#[InBody] public string $content,
	)
	{
	}

}
<?php declare(strict_types = 1);

namespace Tests\Common;

final readonly class ArticleTagInput
{

	public function __construct(
		public string $name,
	)
	{
	}

}

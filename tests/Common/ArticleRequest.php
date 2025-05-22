<?php declare(strict_types = 1);

namespace Tests\Common;

use DateTimeImmutable;

final readonly class ArticleRequest
{

	public function __construct(
		public string $subject,
		public string $content,
		public DateTimeImmutable $publishedAt,
		public int $authorId,
	)
	{
	}

}

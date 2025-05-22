<?php declare(strict_types = 1);

namespace Tests\Common;

use DateTimeImmutable;
use ShipMonk\InputMapper\Compiler\Mapper\Optional;

final readonly class ArticleInput
{

	/**
	 * @param list<ArticleTagInput> $tags
	 */
	public function __construct(
		public string $subject,
		public string $content,
		public DateTimeImmutable $publishedAt,
		public int $authorId,
		#[Optional]
		public ?int $articleId = null,
		#[Optional(default: [])]
		public array $tags = [],
	)
	{
	}

}

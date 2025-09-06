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

enum Status: string
{
	case DRAFT = 'draft';
	case PUBLISHED = 'published';
	case ARCHIVED = 'archived';
}

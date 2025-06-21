<?php declare(strict_types = 1);

namespace Tests\Fixtures;

use Shredio\RequestMapper\Attribute\InHeader;
use Shredio\RequestMapper\Attribute\InPath;
use Shredio\RequestMapper\Attribute\InQuery;

final readonly class UserInput
{

	public function __construct(
		#[InPath('userId')] public int $id,
		#[InQuery] public string $filter = '',
		#[InHeader('X-API-Key')] public string $apiKey = '',
	)
	{
	}

}
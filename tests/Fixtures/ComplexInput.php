<?php declare(strict_types = 1);

namespace Tests\Fixtures;

use Shredio\RequestMapper\Attribute\InAttribute;
use Shredio\RequestMapper\Attribute\InBody;
use Shredio\RequestMapper\Attribute\InHeader;
use Shredio\RequestMapper\Attribute\InPath;
use Shredio\RequestMapper\Attribute\InQuery;
use Shredio\RequestMapper\Attribute\InServer;

final readonly class ComplexInput
{

	public function __construct(
		#[InPath] public int $pathId,
		#[InQuery] public string $queryParam,
		#[InBody] public string $bodyContent,
		#[InHeader] public string $headerValue,
		#[InAttribute] public string $attributeValue,
		#[InServer('HTTP_HOST')] public string $serverHost,
		#[InPath('customPathName')] public string $customPath = 'default',
		#[InQuery('customQueryName')] public int $customQuery = 10,
	)
	{
	}

}
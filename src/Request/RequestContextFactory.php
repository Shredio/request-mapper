<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Request;

use Shredio\RequestMapper\Attribute\RequestParam;

interface RequestContextFactory
{

	/**
	 * @param array<non-empty-string, RequestParam|RequestLocation> $paramConfig
	 */
	public function create(array $paramConfig = []): RequestContext;

}

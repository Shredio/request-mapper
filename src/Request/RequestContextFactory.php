<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Request;

use Shredio\RequestMapper\Attribute\RequestParam;

interface RequestContextFactory
{

	/**
	 * @param array<non-empty-string, RequestParam|RequestLocation> $paramConfig
	 * @param array<non-empty-string, mixed> $staticValues
	 */
	public function create(array $paramConfig = [], ?RequestLocation $location = null, array $staticValues = []): RequestContext;

}

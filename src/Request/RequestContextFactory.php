<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Request;

use Shredio\RequestMapper\RequestMapperConfiguration;

interface RequestContextFactory
{

	public function create(?RequestMapperConfiguration $configuration = null): RequestContext;

}

<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Request\Exception;

use Shredio\RequestMapper\Exception\RuntimeException;

final class MissingHttpBodyException extends RuntimeException
{

	public function __construct(string $parameterName)
	{
		parent::__construct(sprintf('Missing HTTP body for parameter "%s".', $parameterName));
	}

}

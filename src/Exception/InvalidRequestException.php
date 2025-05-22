<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Exception;

use RuntimeException;
use ShipMonk\InputMapper\Runtime\Exception\MappingFailedException;

final class InvalidRequestException extends RuntimeException
{

	public function __construct(
		private readonly MappingFailedException $exception,
	)
	{
		parent::__construct($exception->getMessage(), $exception->getCode(), $exception->getPrevious());
	}

	/**
	 * @return list<string|int>
	 */
	public function getPath(): array
	{
		return $this->exception->getPath();
	}

}

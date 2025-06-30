<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Request\Exception;

use Shredio\Problem\Violation\Violation;
use Shredio\RequestMapper\Exception\RuntimeException;
use Throwable;

final class InvalidRequestException extends RuntimeException
{

	/**
	 * @param list<Violation> $violations
	 */
	public function __construct(
		string $target,
		public readonly array $violations,
		?Throwable $previous = null
	)
	{
		parent::__construct(sprintf(
			"Mapping from request to \"%s\" failed. Reasons:\n%s",
			$target,
			implode("\n", array_map(
				static fn(Violation $violation): string => $violation->debugString(),
				$violations
			))
		), previous: $previous);
	}

}

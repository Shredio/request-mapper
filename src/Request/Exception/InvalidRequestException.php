<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Request\Exception;

use Shredio\Problem\Violation\Violation;
use Shredio\RequestMapper\Exception\RuntimeException;
use Throwable;

final class InvalidRequestException extends RuntimeException
{

	/**
	 * @param string|list<string> $target
	 * @param list<Violation> $violations
	 */
	public function __construct(
		public string|array $target,
		public readonly array $violations,
		?Throwable $previous = null
	)
	{
		parent::__construct(sprintf(
			"Mapping from request to \"%s\" failed. Reasons:\n%s",
			$this->stringifyTarget($target),
			implode("\n", array_map(
				static fn(Violation $violation): string => $violation->debugString(),
				$violations
			))
		), previous: $previous);
	}

	/**
	 * @param list<self> $exceptions
	 * @return self
	 */
	public static function merge(array $exceptions): self
	{
		$violations = [];
		$targets = [];
		$previous = null;
		foreach ($exceptions as $exception) {
			$exceptionPrevious = $exception->getPrevious();
			if ($exceptionPrevious !== null) {
				$previous = $exceptionPrevious;
			}

			$violations = array_merge($violations, $exception->violations);
			$targets = array_merge($targets, is_array($exception->target) ? $exception->target : [$exception->target]);
		}

		return new self(
			target: $targets,
			violations: $violations,
			previous: $previous,
		);
	}

	/**
	 * @param string|list<string> $target
	 */
	private function stringifyTarget(array|string $target): string
	{
		if (is_array($target)) {
			if (count($target) > 1) {
				return sprintf('multiple targets ["%s"]', implode('", "', $target));
			}

			$target = $target[0];
		}

		return sprintf('"%s"', $target);
	}

}

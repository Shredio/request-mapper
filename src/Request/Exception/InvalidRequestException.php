<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Request\Exception;

use Shredio\Problem\Violation\Violation;
use Shredio\RequestMapper\Exception\RuntimeException;
use Throwable;

/**
 * Base class for request mapping failures.
 *
 * Two concrete subclasses distinguish the failure kind so the application can
 * map them to different HTTP responses:
 * - {@see StructuralRequestException}: the input cannot be assembled into the target type (typically HTTP 400)
 * - {@see ValidationRequestException}: the input has the right shape but violates a constraint (typically HTTP 422)
 */
abstract class InvalidRequestException extends RuntimeException
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
			"Mapping from request to %s failed. Reasons:\n%s",
			$this->stringifyTarget($target),
			implode("\n", array_map(
				static fn(Violation $violation): string => $violation->debugString(),
				$violations
			))
		), previous: $previous);
	}

	/**
	 * Merges multiple exceptions into a single one.
	 *
	 * A single structural failure takes precedence: if any of the given exceptions is
	 * a {@see StructuralRequestException}, the result is structural, otherwise validation.
	 *
	 * @param list<self> $exceptions
	 */
	public static function merge(array $exceptions): self
	{
		$violations = [];
		$targets = [];
		$previous = null;
		$isStructural = false;
		foreach ($exceptions as $exception) {
			$exceptionPrevious = $exception->getPrevious();
			if ($exceptionPrevious !== null) {
				$previous = $exceptionPrevious;
			}

			if ($exception instanceof StructuralRequestException) {
				$isStructural = true;
			}

			$violations = array_merge($violations, $exception->violations);
			$targets = array_merge($targets, is_array($exception->target) ? $exception->target : [$exception->target]);
		}

		if ($isStructural) {
			return new StructuralRequestException($targets, $violations, $previous);
		}

		return new ValidationRequestException($targets, $violations, $previous);
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

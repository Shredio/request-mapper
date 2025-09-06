<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Filter;

use Shredio\Problem\Violation\Violation;
use Shredio\RequestMapper\Exception\LogicException;

final readonly class FilterResult
{

	/** @var (callable(string $field): Violation)|null */
	private mixed $violation;

	/**
	 * @param (callable(string $field): Violation)|null $violation
	 */
	public function __construct(
		public mixed $value,
		public bool $isValid = true,
		?callable $violation = null,
	)
	{
		$this->violation = $violation;

		if ($violation !== null && $this->isValid) {
			throw new LogicException('Violation callable must be null if isValid is true');
		}
	}

	public function getViolation(string $field): ?Violation
	{
		if ($this->violation === null) {
			return null;
		}

		return ($this->violation)($field);
	}

}

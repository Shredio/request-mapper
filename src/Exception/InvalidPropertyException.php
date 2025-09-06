<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Exception;

use Shredio\Problem\Violation\Violation;

/**
 * @internal
 */
final class InvalidPropertyException extends RuntimeException
{

	/** @var callable(string $fieldName): Violation */
	private readonly mixed $createViolation;

	/**
	 * @param callable(string $fieldName): Violation $createViolation
	 */
	public function __construct(
		callable $createViolation,
	)
	{
		parent::__construct();

		$this->createViolation = $createViolation;
	}

	/**
	 * @return list<Violation>
	 */
	public function getViolations(string $fieldName): array
	{
		return [($this->createViolation)($fieldName)];
	}

}

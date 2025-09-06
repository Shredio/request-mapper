<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Exception;

use Shredio\Problem\Exception\ViolationAwareException;
use Shredio\Problem\Violation\Violation;

final class InvalidValueException extends RuntimeException implements ViolationAwareException
{

	/** @var callable(?string $fieldName): list<Violation> */
	private mixed $factory;

	/**
	 * @param callable(?string $fieldName): list<Violation> $factory
	 */
	public function __construct(callable $factory)
	{
		parent::__construct('Invalid value provided.');

		$this->factory = $factory;
	}

	public function getViolations(?string $fieldName = null): array
	{
		return ($this->factory)($fieldName);
	}

}

<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Error;

use Shredio\Problem\Violation\Violation;
use Shredio\RequestMapper\Exception\LogicException;

final class RequestMapperErrorCollector
{

	/** @var int<-1, max> */
	private static int $index = -1;

	/** @var list<Violation> */
	private static array $violations = [];

	private function __construct()
	{
		// noop
	}

	public static function addViolation(Violation $violation): void
	{
		self::$violations[] = $violation;
	}

	/**
	 * @internal
	 * @return callable(): list<Violation>
	 */
	public static function capture(): callable
	{
		self::$index++;
		$isRoot = self::$index === 0;

		return static function() use ($isRoot): array {
			$newIndex = self::$index - 1;
			if ($newIndex >= 0) {
				self::$index = $newIndex;
			} else if ($isRoot) {
				self::$index = -1;
			} else {
				throw new LogicException('Unbalanced capture calls');
			}

			if (!$isRoot) {
				return [];
			}

			$violations = self::$violations;
			self::$violations = [];

			return $violations;
		};
	}

}

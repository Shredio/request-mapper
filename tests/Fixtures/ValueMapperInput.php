<?php declare(strict_types = 1);

namespace Tests\Fixtures;

final readonly class ValueMapperInput
{

	public function __construct(
		public StringValueObject $stringObject,
		public IntValueObject $intObject,
	)
	{
	}

}

abstract class ScalarValueObject
{

	abstract public static function getType(): string;

}

class StringValueObject extends ScalarValueObject
{

	public function __construct(
		public string $value,
	) {
	}

	public static function getType(): string
	{
		return 'string';
	}

}

class IntValueObject extends ScalarValueObject
{

	public function __construct(
		public int $value,
	) {
	}

	public static function getType(): string
	{
		return 'int';
	}

}

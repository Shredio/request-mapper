<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Filter;

use BackedEnum;
use InvalidArgumentException;
use ReflectionEnum;
use ReflectionNamedType;
use ReflectionType;
use Shredio\Problem\Violation\Violation;
use Shredio\RequestMapper\Error\RequestMapperErrorFactory;
use ValueError;

final readonly class FilterVar
{

	public const int String = 0;
	public const int Int = 1;
	public const int Float = 2;
	public const int Bool = 3;

	public const int StringArray = 10;
	public const int IntArray = 11;
	public const int FloatArray = 12;
	public const int BoolArray = 13;

	public const int StringOrNull = 100;
	public const int IntOrNull = 101;
	public const int FloatOrNull = 102;
	public const int BoolOrNull = 103;

	public const int StringOrNullArray = 110;
	public const int IntOrNullArray = 111;
	public const int FloatOrNullArray = 112;
	public const int BoolOrNullArray = 113;

	/** @var (callable(mixed $value, bool $returnScalar): FilterResult)|null */
	private mixed $postProcessor;

	/**
	 * @param 'string'|'int'|'float'|'bool' $type
	 * @param (callable(mixed $value, bool $returnScalar): FilterResult)|null $postProcessor Optional callable to process the value after filtering (without null).
	 */
	public function __construct(
		private int $filter,
		private string $type,
		private ?int $flags = null,
		private mixed $options = null,
		private bool $nullable = false,
		?callable $postProcessor = null,
	)
	{
		$this->postProcessor = $postProcessor;
	}

	public static function getValidTypeFromReflection(ReflectionType $reflectionType): ?string
	{
		if (!$reflectionType instanceof ReflectionNamedType) {
			return null;
		}

		$name = $reflectionType->getName();
		if ($reflectionType->isBuiltin()) {
			return match ($name) {
				'int', 'float', 'bool', 'string' => $name,
				default => null,
			};
		}

		if (is_subclass_of($name, BackedEnum::class)) {
			return $name;
		}

		return null;
	}

	/**
	 * @throws InvalidArgumentException
	 */
	public static function createFromType(string $type, bool $nullable): self
	{
		$arguments = match ($type) {
			'int' => self::getFilterMap(self::Int),
			'float' => self::getFilterMap(self::Float),
			'bool' => self::getFilterMap(self::Bool),
			'string' => self::getFilterMap(self::String),
			default => null,
 		};

		if ($arguments === null) {
			if (!is_subclass_of($type, BackedEnum::class)) {
				throw new InvalidArgumentException("Unsupported type specified: $type");
			}

			$enumInnerType = (new ReflectionEnum($type))->getBackingType()?->getName();
			$arguments = match ($enumInnerType) {
				'int' => self::getFilterMap(self::Int),
				'string' => self::getFilterMap(self::String),
				default => throw new InvalidArgumentException("Unsupported enum backing type specified: $type"),
			};
			$arguments['type'] = $enumInnerType;
			$arguments['postProcessor'] = static function(mixed $value, bool $returnScalar) use ($type): FilterResult {
				if (!is_string($value) && !is_int($value)) {
					return new FilterResult($value, false);
				}
				try {
					$enum = $type::from($value);
					return new FilterResult($returnScalar ? $value : $enum, true);
				} catch (ValueError) {
					return new FilterResult(
						$value,
						false,
						static fn (string $field): Violation => RequestMapperErrorFactory::invalidChoice($field, array_map(
							fn (BackedEnum $enum): string|int => $enum->value,
							$type::cases(),
						)),
					);
				}
			};
		}

		$arguments['nullable'] = $nullable;
		return new self(...$arguments); // @phpstan-ignore-line
	}

	/**
	 * @param FilterVar::* $filter
	 */
	public static function createFromIntFilter(int $filter): self
	{
		if ($filter >= 100) {
			$isNullable = true;
			$filter -= 100;
		} else {
			$isNullable = false;
		}

		if ($filter >= 10) {
			$isArray = true;
			$filter -= 10;
		} else {
			$isArray = false;
		}

		$args = self::getFilterMap($filter);
		if ($args === null) {
			$original = $filter + ($isArray ? 10 : 0) + ($isNullable ? 100 : 0);

			throw new InvalidArgumentException("Invalid filter specified: $original");
		}

		$args['nullable'] = $isNullable;
		if ($isArray) {
			$args['flags'] = FILTER_REQUIRE_ARRAY;
		} else {
			$args['flags'] = FILTER_REQUIRE_SCALAR;
		}

		return new self(...$args);
	}

	/**
	 * @return array{ filter: int, type: 'string'|'int'|'float'|'bool', options?: mixed }|null
	 */
	private static function getFilterMap(int $filter): ?array
	{
		return [
			0 => ['filter' => FILTER_CALLBACK, 'type' => 'string', 'options' => [self::class, 'stringFilter']],
			1 => ['filter' => FILTER_VALIDATE_INT, 'type' => 'int'],
			2 => ['filter' => FILTER_VALIDATE_FLOAT, 'type' => 'float'],
			3 => ['filter' => FILTER_VALIDATE_BOOL, 'type' => 'bool'],
		][$filter] ?? null;
	}

	public function call(mixed $value, bool $returnScalar = false): FilterResult
	{
		if ($value === null) {
			return new FilterResult($value, $this->nullable);
		}

		$options = [
			'flags' => FILTER_NULL_ON_FAILURE,
		];
		if ($this->options !== null) {
			$options['options'] = $this->options;
		}
		if ($this->flags !== null) {
			$options['flags'] |= $this->flags;
		}

		$filtered = filter_var($value, $this->filter, $options);
		if ($filtered === null) {
			return new FilterResult($value, false);
		}

		return $this->finalValue($filtered, $returnScalar);
	}

	/**
	 * @internal
	 */
	public static function stringFilter(mixed $value): ?string
	{
		if ($value === null) {
			return null;
		}
		if (is_bool($value)) {
			return $value ? '1' : '0';
		}

		return is_scalar($value) ? (string) $value : null;
	}

	public function __invoke(mixed $value): FilterResult
	{
		return $this->call($value);
	}

	private function finalValue(mixed $value, bool $returnScalar): FilterResult
	{
		if ($this->postProcessor !== null) {
			return ($this->postProcessor)($value, $returnScalar);
		}

		return new FilterResult($value, true);
	}

	/**
	 * @return 'string'|'int'|'float'|'bool'
	 */
	public function getType(): string
	{
		return $this->type;
	}

	/**
	 * @return 'string'|'int'|'float'|'bool'|'string|null'|'int|null'|'float|null'|'bool|null'
	 */
	public function getNullableType(): string
	{
		return $this->type . ($this->nullable ? '|null' : '');
	}

	public function createViolation(string $field): Violation
	{
		return RequestMapperErrorFactory::invalidTypeMessage($field, $this->getNullableType());
	}

}

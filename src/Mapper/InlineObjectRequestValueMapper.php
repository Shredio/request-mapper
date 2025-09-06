<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Mapper;

use Shredio\RequestMapper\Error\RequestMapperErrorFactory;
use Shredio\RequestMapper\Exception\InvalidValueException;
use Shredio\RequestMapper\Filter\FilterVar;
use TypeError;

/**
 * @template T of object
 * @implements RequestValueMapper<T>
 */
final readonly class InlineObjectRequestValueMapper implements RequestValueMapper
{

	/** @var callable(mixed $value): ?T */
	private mixed $factory;

	/**
	 * @param class-string<T> $className
	 * @param 'string'|'int'|'float'|'bool' $scalarType The scalar type that the object can be constructed from
	 * @param (callable(mixed $value): ?T)|null $factory Optional factory to create the object. If not provided, the object will be created using the default constructor
	 */
	public function __construct(
		private string $className,
		private string $scalarType,
		private bool $nullable = false,
		?callable $factory = null,
	)
	{
		$this->factory = $factory ?? [$this, 'defaultFactory'];
	}

	public function getSupportedType(): string
	{
		return $this->className;
	}

	/**
	 * @throws InvalidValueException
	 */
	public function map(mixed $input, string $className): object
	{
		$object = ($this->factory)($input);
		if ($object === null) { // null means an invalid type of value
			throw new InvalidValueException(fn (?string $fieldName): array => [RequestMapperErrorFactory::invalidTypeMessage($fieldName, $this->scalarType)]);
		}

		return $object;
	}

	public function convert(mixed $input, string $className): mixed
	{
		$filter = FilterVar::createFromType($this->scalarType, $this->nullable);
		return $filter->call($input, true)->value;
	}

	/**
	 * @return T|null
	 */
	private function defaultFactory(mixed $value): ?object
	{
		try {
			return new ($this->className)($value);
		} catch (TypeError) {
			return null;
		}
	}

}

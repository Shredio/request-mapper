<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Field;

final readonly class FieldMirror
{

	public function __construct(
		public string $name,
		public bool $hasDefaultValue,
		public mixed $defaultValue,
		public FieldType $type,
	)
	{
	}

	/**
	 * @param class-string $className
	 */
	public static function createFromReflectionParameter(string $className, \ReflectionParameter $parameter): self
	{
		return new self(
			$parameter->getName(),
			$parameter->isDefaultValueAvailable(),
			$parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null,
			new FieldTypeParamReflection($className, $parameter),
		);
	}

}

<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Field;

use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use Shredio\RequestMapper\Exception\LogicException;

final readonly class FieldTypeParamReflection implements FieldType
{

	/**
	 * @param class-string $className
	 */
	public function __construct(
		private string $className,
		private ReflectionParameter $parameter,
	)
	{
	}

	public function __toString(): string
	{
		return (string) $this->parameter->getType();
	}

	public function getSingleType(): string
	{
		$parameterName = $this->parameter->getName();
		$type = $this->parameter->getType();
		if ($type === null) {
			throw new LogicException(sprintf(
				'Parameter %s of class %s has no type defined, add the type or use parameter configuration.',
				$parameterName,
				$this->className,
			));
		}
		if (!$type instanceof ReflectionNamedType) {
			throw new LogicException(sprintf(
				'Parameter %s of class %s has a union or intersection type, these are not supported, use parameter configuration to specify behavior.',
				$parameterName,
				$this->className,
			));
		}

		return $type->getName();
	}

	public function allowsNull(): bool
	{
		$type = $this->parameter->getType();
		if ($type === null) {
			throw new LogicException(sprintf(
				'Parameter %s of class %s has no type defined, add the type or use parameter configuration.',
				$this->parameter->getName(),
				$this->className,
			));
		}

		return $type->allowsNull();
	}

	public function isBuiltIn(): bool
	{
		$type = $this->parameter->getType();
		if ($type === null) {
			throw new LogicException(sprintf(
				'Parameter %s of class %s has no type defined, add the type or use parameter configuration.',
				$this->parameter->getName(),
				$this->className,
			));
		}
		if (!$type instanceof ReflectionNamedType) {
			throw new LogicException(sprintf(
				'Parameter %s of class %s has a union or intersection type, these are not supported, use parameter configuration to specify behavior.',
				$this->parameter->getName(),
				$this->className,
			));
		}

		return $type->isBuiltin();
	}

}

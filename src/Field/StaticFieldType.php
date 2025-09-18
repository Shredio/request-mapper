<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Field;

final readonly class StaticFieldType implements FieldType
{

	public function __construct(
		private ?string $type,
		private bool $allowsNull = false,
	)
	{
	}

	public function __toString(): string
	{
		return $this->type ?? 'mixed';
	}

	public function getSingleType(): string
	{
		if ($this->type === null) {
			return 'mixed';
		}
		if (str_contains($this->type, '|') || str_contains($this->type, '&')) {
			throw new \LogicException('Union and intersection types are not supported, use parameter configuration to specify behavior.');
		}

		return $this->type;
	}

	public function allowsNull(): bool
	{
		return $this->allowsNull;
	}

	public function isBuiltIn(): bool
	{
		return in_array($this->type, ['int', 'float', 'string', 'bool', 'array', 'object', 'callable', 'iterable', 'mixed'], true);
	}

}

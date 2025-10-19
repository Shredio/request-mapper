<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Request;

use Shredio\TypeSchema\Types\Type;

final readonly class SingleRequestParameter
{

	/**
	 * @param Type<mixed> $typeSchema
	 */
	public function __construct(
		public string $name,
		public Type $typeSchema,
		public bool $isNullable,
		public bool $isOptional,
		public mixed $defaultValue = null,
	)
	{
	}

}

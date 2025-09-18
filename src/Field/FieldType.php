<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Field;

use Stringable;

interface FieldType extends Stringable
{

	public function getSingleType(): string;

	public function allowsNull(): bool;

	public function isBuiltIn(): bool;

}

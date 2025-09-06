<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Error;

use Shredio\Problem\Helper\ProblemHelper;
use Shredio\Problem\Violation\FieldViolation;
use Shredio\Problem\Violation\GlobalViolation;
use Shredio\Problem\Violation\Violation;
use Symfony\Component\Translation\TranslatableMessage;

final readonly class RequestMapperErrorFactory
{

	public static function missingField(?string $field): Violation
	{
		$messages = [new TranslatableMessage('This field is missing.')];
		if ($field === null) {
			return new GlobalViolation($messages);
		}
		return new FieldViolation($field, [new TranslatableMessage('This field is missing.')]);
	}

	public static function extraField(?string $field): Violation
	{
		$messages = [new TranslatableMessage('This field is not allowed.')];
		if ($field === null) {
			return new GlobalViolation($messages);
		}
		return new FieldViolation($field, $messages);
	}

	/**
	 * @param list<string|int> $allowedValues
	 */
	public static function invalidChoice(?string $field, array $allowedValues): Violation
	{
		$count = count($allowedValues);
		if ($count === 0) {
			$message = new TranslatableMessage('This value is not valid.');
		} else if ($count === 1) {
			$message = new TranslatableMessage('This value must be {{ value }}.', ['{{ value }}' => $allowedValues[0]]);
		} else {
			$message = new TranslatableMessage('This value must be one of {{ values }}.', ['{{ values }}' => ProblemHelper::humanImplode($allowedValues, '', '"')]);
		}

		if ($field === null) {
			return new GlobalViolation([$message]);
		}

		return new FieldViolation($field, [$message]);
	}

	public static function invalidTypeMessage(?string $field, string $expectedType): Violation
	{
		$messages = [new TranslatableMessage('This value should be of type {{ type }}.', ['{{ type }}' => $expectedType])];
		if ($field === null) {
			return new GlobalViolation($messages);
		}

		return new FieldViolation($field, $messages);
	}

}

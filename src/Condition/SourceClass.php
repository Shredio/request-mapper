<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Condition;

use Symfony\Component\ObjectMapper\ConditionCallableInterface;

/**
 * @template T of object
 *
 * @implements ConditionCallableInterface<object, T>
 */
final readonly class SourceClass implements ConditionCallableInterface
{

	/**
	 * @param class-string<T> $className
	 */
	public function __construct(private string $className)
	{
	}

	public function __invoke(mixed $value, object $source, ?object $target): bool
	{
		return $source instanceof $this->className;
	}

}

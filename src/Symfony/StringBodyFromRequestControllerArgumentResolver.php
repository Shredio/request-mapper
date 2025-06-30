<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Symfony;

use Shredio\RequestMapper\Attribute\StringBodyFromRequest;
use Shredio\RequestMapper\Request\Exception\MissingHttpBodyException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final readonly class StringBodyFromRequestControllerArgumentResolver implements ValueResolverInterface
{

	/**
	 * @return list<string|null>
	 * @throws MissingHttpBodyException
	 */
	public function resolve(Request $request, ArgumentMetadata $argument): iterable
	{
		$attribute = $argument->getAttributesOfType(StringBodyFromRequest::class)[0] ?? null;

		if (!$attribute) {
			return [];
		}

		$content = $request->getContent();

		if ($content === '' && !$argument->isNullable()) {
			throw new MissingHttpBodyException($argument->getName());
		}

		if ($content === '' && $argument->isNullable()) {
			return [null];
		}

		return [$content];
	}

}

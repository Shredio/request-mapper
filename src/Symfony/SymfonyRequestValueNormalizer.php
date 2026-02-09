<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Symfony;

use Shredio\RequestMapper\Request\RequestLocation;
use Shredio\RequestMapper\Request\RequestValueNormalizer;

final readonly class SymfonyRequestValueNormalizer implements RequestValueNormalizer
{

	public function normalize(mixed $value, RequestLocation $location): mixed
	{
		if ($location === RequestLocation::Header && is_array($value)) {
			// Symfony returns headers as array, but we want single value
			return implode(', ', $value);
		}

		return $value;
	}

}

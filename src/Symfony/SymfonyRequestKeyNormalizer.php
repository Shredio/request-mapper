<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Symfony;

use Shredio\RequestMapper\Request\RequestKeyNormalizer;
use Shredio\RequestMapper\Request\RequestLocation;

final readonly class SymfonyRequestKeyNormalizer implements RequestKeyNormalizer
{

	public function normalize(string $key, RequestLocation $location): string
	{
		if ($location === RequestLocation::Header) {
			return strtolower($key);
		}

		return $key;
	}

}

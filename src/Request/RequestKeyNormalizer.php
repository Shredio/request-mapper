<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Request;

interface RequestKeyNormalizer
{

	public function normalize(string $key, RequestLocation $location): string;

}

<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Request;

interface RequestValueNormalizer
{

	public function normalize(mixed $value, RequestLocation $location): mixed;

}

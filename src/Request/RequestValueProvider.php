<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Request;

interface RequestValueProvider
{

	/**
	 * @return mixed[]
	 */
	public function getValues(RequestLocation $location): array;

}

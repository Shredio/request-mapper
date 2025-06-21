<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Request;

interface RequestContext
{

	/**
	 * @return class-string|null
	 */
	public function getMediatorClass(): ?string;

	public function getDefaultRequestLocation(): RequestLocation;

	public function getRequestValues(): RequestValues;

	public function getRequestValuesByLocation(RequestLocation $location): RequestValues;

	public function getPath(): ?string;

	public function isExtraParametersAllowed(): bool;

}

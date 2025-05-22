<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Context;

use Shredio\RequestMapper\Enum\RequestLocation;
use Shredio\RequestMapper\Parameter\RequestParameter;

interface RequestMappingContext
{

	/**
	 * @return list<RequestParameter>
	 */
	public function getRequestParameters(): array;

	/**
	 * @return class-string|null
	 */
	public function getMediatorClass(): ?string;

	/**
	 * Returns the request values for the current request by a given location.
	 *
	 * @return array<string, mixed>
	 */
	public function getRequestValues(): array;

	/**
	 * @return array<string, mixed>
	 */
	public function getRequestValuesByLocation(RequestLocation $location): array;

//	/**
//	 * @return array<string, mixed>
//	 */
//	public function getQuery(): array;
//
//	/**
//	 * @return array<string, mixed>
//	 */
//	public function getHeaders(): array;
//
//	/**
//	 * @return array<string, mixed>
//	 */
//	public function getBody(): array;
//
//	/**
//	 * @return array<string, mixed>
//	 */
//	public function getAttributes(): array;
//
//	/**
//	 * @return array<string, mixed>
//	 */
//	public function getServer(): array;


}

<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Request;

use Shredio\RequestMapper\Attribute\RequestParam;

interface RequestContext
{

	/**
	 * @return array<non-empty-string, RequestParam|RequestLocation>
	 */
	public function getParamConfigs(): array;

	/**
	 * @return class-string|null
	 */
	public function getMediatorClass(): ?string;

	public function getDefaultRequestLocation(): RequestLocation;

	public function isTypeStrictByRequestLocation(RequestLocation $location): bool;

	/**
	 * @return mixed[]
	 */
	public function getRequestValues(): array;

	/**
	 * @return mixed[]
	 */
	public function getRequestValuesByLocation(RequestLocation $location): array;

	public function getPath(): ?string;

	public function isExtraParametersAllowed(): bool;

	public function normalizeKey(string $key, RequestLocation $location): string;

	public function filterValue(mixed $value, RequestLocation $location): mixed;

}

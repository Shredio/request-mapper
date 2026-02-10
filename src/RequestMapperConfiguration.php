<?php declare(strict_types = 1);

namespace Shredio\RequestMapper;

use Shredio\RequestMapper\Attribute\RequestParam;
use Shredio\RequestMapper\Request\RequestLocation;

final readonly class RequestMapperConfiguration
{

	/**
	 * @param array<non-empty-string, RequestParam|RequestLocation> $parameters
	 * @param array<non-empty-string, mixed> $presetValues
	 */
	public function __construct(
		public array $parameters = [],
		public ?RequestLocation $location = null,
		public array $presetValues = [],
		public bool $allowExtraParameters = false,
	)
	{
	}

	/**
	 * @param array<non-empty-string, mixed> $presetValues
	 */
	public function withPresetValues(array $presetValues): self
	{
		return new self(
			parameters: $this->parameters,
			location: $this->location,
			presetValues: $presetValues,
		);
	}

	/**
	 * @param array<non-empty-string, mixed> $presetValues
	 */
	public function withMergedPresetValues(array $presetValues): self
	{
		return new self(
			parameters: $this->parameters,
			location: $this->location,
			presetValues: array_merge($this->presetValues, $presetValues),
		);
	}

	/**
	 * @param non-empty-string $key
	 */
	public function withPresetValue(string $key, mixed $value): self
	{
		return new self(
			parameters: $this->parameters,
			location: $this->location,
			presetValues: array_merge($this->presetValues, [$key => $value]),
		);
	}

}

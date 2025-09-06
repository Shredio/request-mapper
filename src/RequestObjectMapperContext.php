<?php declare(strict_types = 1);

namespace Shredio\RequestMapper;

class RequestObjectMapperContext
{

	/** @var array<string, string> */
	private array $parameterNameMapping = [];

	public function addParameterNameMapping(string $sourceName, string $targetName): void
	{
		$this->parameterNameMapping[$targetName] = $sourceName;
	}

	public function getCorrectParameterName(string $name): string
	{
		return $this->parameterNameMapping[$name] ?? $name;
	}

}

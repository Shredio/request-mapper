<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Attribute;

use Attribute;
use Shredio\RequestMapper\Filter\FilterResult;
use Shredio\RequestMapper\Request\RequestLocation;
use Shredio\RequestMapper\Filter\FilterVar;

#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class RequestParam
{

	private ?FilterVar $filter;

	/**
	 * @param FilterVar|FilterVar::*|null $filter
	 */
	public function __construct(
		public ?string $sourceKey = null,
		public ?RequestLocation $location = null,
		FilterVar|int|null $filter = null,
	)
	{
		$this->filter = is_int($filter) ? FilterVar::createFromIntFilter($filter) : $filter;
	}

	public function getFilter(): ?FilterVar
	{
		return $this->filter;
	}

}

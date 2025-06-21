<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class InServer
{

	public function __construct(
		public ?string $name = null,
	)
	{
	}

}
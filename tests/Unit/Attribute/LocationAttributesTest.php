<?php declare(strict_types = 1);

namespace Tests\Unit\Attribute;

use Shredio\RequestMapper\Attribute\InAttribute;
use Shredio\RequestMapper\Attribute\InBody;
use Shredio\RequestMapper\Attribute\InHeader;
use Shredio\RequestMapper\Attribute\InPath;
use Shredio\RequestMapper\Attribute\InQuery;
use Shredio\RequestMapper\Attribute\InServer;
use Tests\TestCase;

final class LocationAttributesTest extends TestCase
{

	public function testInPathAttribute(): void
	{
		$attribute = new InPath();
		self::assertNull($attribute->name);

		$attributeWithName = new InPath('customName');
		self::assertSame('customName', $attributeWithName->name);
	}

	public function testInQueryAttribute(): void
	{
		$attribute = new InQuery();
		self::assertNull($attribute->name);

		$attributeWithName = new InQuery('customName');
		self::assertSame('customName', $attributeWithName->name);
	}

	public function testInBodyAttribute(): void
	{
		$attribute = new InBody();
		self::assertNull($attribute->name);

		$attributeWithName = new InBody('customName');
		self::assertSame('customName', $attributeWithName->name);
	}

	public function testInHeaderAttribute(): void
	{
		$attribute = new InHeader();
		self::assertNull($attribute->name);

		$attributeWithName = new InHeader('X-Custom-Header');
		self::assertSame('X-Custom-Header', $attributeWithName->name);
	}

	public function testInAttributeAttribute(): void
	{
		$attribute = new InAttribute();
		self::assertNull($attribute->name);

		$attributeWithName = new InAttribute('customName');
		self::assertSame('customName', $attributeWithName->name);
	}

	public function testInServerAttribute(): void
	{
		$attribute = new InServer();
		self::assertNull($attribute->name);

		$attributeWithName = new InServer('HTTP_HOST');
		self::assertSame('HTTP_HOST', $attributeWithName->name);
	}

}
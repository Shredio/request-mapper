<?php declare(strict_types = 1);

namespace Tests\Unit\Field;

use Shredio\RequestMapper\Exception\FieldNotExistsException;
use Shredio\RequestMapper\Field\Field;
use Tests\TestCase;

final class FieldTest extends TestCase
{

	public function testCreateSimpleField(): void
	{
		$field = Field::create('name');
		
		self::assertSame('name', $field->getFullPath());
		self::assertFalse($field->hasParent());
		self::assertNull($field->getParentPath());
	}

	public function testCreateNestedField(): void
	{
		$field = Field::create('user.name');
		
		self::assertSame('user.name', $field->getFullPath());
		self::assertTrue($field->hasParent());
		self::assertSame('user', $field->getParentPath());
	}

	public function testCreateDeeplyNestedField(): void
	{
		$field = Field::create('user.profile.address.street');
		
		self::assertSame('user.profile.address.street', $field->getFullPath());
		self::assertTrue($field->hasParent());
		self::assertSame('user.profile.address', $field->getParentPath());
	}

	public function testGetValueFromSimpleField(): void
	{
		$field = Field::create('name');
		$values = ['name' => 'John', 'age' => 30];
		
		$result = $field->getValueFrom($values);
		
		self::assertSame('John', $result);
	}

	public function testGetValueFromNestedField(): void
	{
		$field = Field::create('user.name');
		$values = [
			'user' => ['name' => 'John', 'age' => 30],
			'other' => 'value'
		];
		
		$result = $field->getValueFrom($values);
		
		self::assertSame('John', $result);
	}

	public function testGetValueFromDeeplyNestedField(): void
	{
		$field = Field::create('user.profile.address.street');
		$values = [
			'user' => [
				'profile' => [
					'address' => [
						'street' => 'Main St',
						'city' => 'New York'
					]
				]
			]
		];
		
		$result = $field->getValueFrom($values);
		
		self::assertSame('Main St', $result);
	}

	public function testGetReferenceValueFromSimpleField(): void
	{
		$field = Field::create('name');
		$values = ['name' => 'John', 'age' => 30];
		
		$result = &$field->getReferenceValueFrom($values);
		
		self::assertSame('John', $result);
		
		$result = 'Jane';
		self::assertSame('Jane', $values['name']);
	}

	public function testGetReferenceValueFromNestedField(): void
	{
		$field = Field::create('user.name');
		$values = [
			'user' => ['name' => 'John', 'age' => 30],
			'other' => 'value'
		];
		
		$result = &$field->getReferenceValueFrom($values);
		
		self::assertSame('John', $result);
		
		$result = 'Jane';
		self::assertSame('Jane', $values['user']['name']);
	}

	public function testThrowsExceptionWhenSimpleFieldDoesNotExist(): void
	{
		$field = Field::create('missing');
		$values = ['name' => 'John'];
		
		$this->expectException(FieldNotExistsException::class);
		$this->expectExceptionMessage('Failed to get value for field "missing": path "missing" does not exist.');
		
		$field->getValueFrom($values);
	}

	public function testThrowsExceptionWhenNestedFieldDoesNotExist(): void
	{
		$field = Field::create('user.missing');
		$values = [
			'user' => ['name' => 'John'],
		];
		
		$this->expectException(FieldNotExistsException::class);
		$this->expectExceptionMessage('Failed to get value for field "user.missing": path "user.missing" does not exist.');
		
		$field->getValueFrom($values);
	}

	public function testThrowsExceptionWhenParentPathDoesNotExist(): void
	{
		$field = Field::create('missing.name');
		$values = [
			'user' => ['name' => 'John'],
		];
		
		$this->expectException(FieldNotExistsException::class);
		$this->expectExceptionMessage('Failed to get value for field "missing.name": path "" does not exist.');
		
		$field->getValueFrom($values);
	}

	public function testThrowsExceptionWhenDeeplyNestedParentPathDoesNotExist(): void
	{
		$field = Field::create('user.profile.missing.street');
		$values = [
			'user' => [
				'profile' => [
					'address' => ['street' => 'Main St']
				]
			]
		];
		
		$this->expectException(FieldNotExistsException::class);
		$this->expectExceptionMessage('Failed to get value for field "user.profile.missing.street": path "user.profile" does not exist.');
		
		$field->getValueFrom($values);
	}

	public function testThrowsExceptionWhenParentIsNotArray(): void
	{
		$field = Field::create('user.name');
		$values = [
			'user' => 'not an array',
		];
		
		$this->expectException(FieldNotExistsException::class);
		$this->expectExceptionMessage('Failed to get value for field "user.name": path "user.name" does not exist.');
		
		$field->getValueFrom($values);
	}

	public function testThrowsExceptionWhenIntermediateParentIsNotArray(): void
	{
		$field = Field::create('user.profile.name');
		$values = [
			'user' => [
				'profile' => 'not an array'
			]
		];
		
		$this->expectException(FieldNotExistsException::class);
		$this->expectExceptionMessage('Failed to get value for field "user.profile.name": path "user.profile.name" does not exist.');
		
		$field->getValueFrom($values);
	}

	public function testHandlesNullValues(): void
	{
		$field = Field::create('name');
		$values = ['name' => null];
		
		$result = $field->getValueFrom($values);
		
		self::assertNull($result);
	}

	public function testHandlesNumericKeys(): void
	{
		$field = Field::create('0');
		$values = [0 => 'first', 1 => 'second'];
		
		$result = $field->getValueFrom($values);
		
		self::assertSame('first', $result);
	}

	public function testHandlesNestedNumericKeys(): void
	{
		$field = Field::create('users.0.name');
		$values = [
			'users' => [
				0 => ['name' => 'John'],
				1 => ['name' => 'Jane']
			]
		];
		
		$result = $field->getValueFrom($values);
		
		self::assertSame('John', $result);
	}

	public function testGetForcedReferenceValueFromSimpleField(): void
	{
		$field = Field::create('name');
		$values = ['name' => 'John'];
		
		$result = &$field->getForcedReferenceValueFrom($values);
		
		self::assertSame('John', $result);
		
		$result = 'Jane';
		self::assertSame('Jane', $values['name']);
	}

	public function testGetForcedReferenceValueFromMissingSimpleField(): void
	{
		$field = Field::create('name');
		$values = [];
		
		$result = &$field->getForcedReferenceValueFrom($values);
		
		self::assertNull($result);
		self::assertArrayHasKey('name', $values);
		self::assertNull($values['name']);
		
		$result = 'John';
		self::assertSame('John', $values['name']);
	}

	public function testGetForcedReferenceValueFromNestedField(): void
	{
		$field = Field::create('user.name');
		$values = [
			'user' => ['name' => 'John'],
		];
		
		$result = &$field->getForcedReferenceValueFrom($values);
		
		self::assertSame('John', $result);
		
		$result = 'Jane';
		self::assertSame('Jane', $values['user']['name']);
	}

	public function testGetForcedReferenceValueFromMissingNestedField(): void
	{
		$field = Field::create('user.name');
		$values = [];
		
		$result = &$field->getForcedReferenceValueFrom($values);
		
		self::assertNull($result);
		self::assertArrayHasKey('user', $values);
		self::assertArrayHasKey('name', $values['user']);
		self::assertNull($values['user']['name']);
		
		$result = 'John';
		self::assertSame('John', $values['user']['name']);
	}

	public function testGetForcedReferenceValueFromPartiallyMissingPath(): void
	{
		$field = Field::create('user.profile.name');
		$values = [
			'user' => ['age' => 30],
		];
		
		$result = &$field->getForcedReferenceValueFrom($values);
		
		self::assertNull($result);
		self::assertArrayHasKey('profile', $values['user']);
		self::assertArrayHasKey('name', $values['user']['profile']);
		self::assertNull($values['user']['profile']['name']);
		
		$result = 'John';
		self::assertSame('John', $values['user']['profile']['name']);
	}

	public function testGetForcedReferenceValueFromOverwritesNonArrayParent(): void
	{
		$field = Field::create('user.name');
		$values = [
			'user' => 'not an array',
		];
		
		$result = &$field->getForcedReferenceValueFrom($values);
		
		self::assertNull($result);
		self::assertIsArray($values['user']);
		self::assertArrayHasKey('name', $values['user']);
		self::assertNull($values['user']['name']);
		
		$result = 'John';
		self::assertSame('John', $values['user']['name']);
	}

	public function testGetForcedReferenceValueFromOverwritesNonArrayIntermediateParent(): void
	{
		$field = Field::create('user.profile.name');
		$values = [
			'user' => [
				'profile' => 'not an array',
				'age' => 30
			]
		];
		
		$result = &$field->getForcedReferenceValueFrom($values);
		
		self::assertNull($result);
		self::assertIsArray($values['user']['profile']);
		self::assertArrayHasKey('name', $values['user']['profile']);
		self::assertNull($values['user']['profile']['name']);
		self::assertSame(30, $values['user']['age']);
		
		$result = 'John';
		self::assertSame('John', $values['user']['profile']['name']);
	}

	public function testGetForcedReferenceValueFromOverwritesNonArrayTarget(): void
	{
		$field = Field::create('user');
		$values = [
			'user' => 'not an array',
		];
		
		$result = &$field->getForcedReferenceValueFrom($values);
		
		self::assertSame('not an array', $result);
		
		$result = ['name' => 'John'];
		self::assertSame(['name' => 'John'], $values['user']);
	}

	public function testUnsetValueFromSimpleField(): void
	{
		$field = Field::create('name');
		$values = ['name' => 'John', 'age' => 30];
		
		$field->unsetValueFrom($values);
		
		self::assertArrayNotHasKey('name', $values);
		self::assertArrayHasKey('age', $values);
		self::assertSame(30, $values['age']);
	}

	public function testUnsetValueFromNestedField(): void
	{
		$field = Field::create('user.name');
		$values = [
			'user' => ['name' => 'John', 'age' => 30],
			'other' => 'value'
		];
		
		$field->unsetValueFrom($values);
		
		self::assertArrayNotHasKey('name', $values['user']);
		self::assertArrayHasKey('age', $values['user']);
		self::assertSame(30, $values['user']['age']);
		self::assertSame('value', $values['other']);
	}

	public function testUnsetValueFromDeeplyNestedField(): void
	{
		$field = Field::create('user.profile.name');
		$values = [
			'user' => [
				'profile' => ['name' => 'John', 'age' => 30],
				'email' => 'john@example.com'
			]
		];
		
		$field->unsetValueFrom($values);
		
		self::assertArrayNotHasKey('name', $values['user']['profile']);
		self::assertArrayHasKey('age', $values['user']['profile']);
		self::assertSame(30, $values['user']['profile']['age']);
		self::assertSame('john@example.com', $values['user']['email']);
	}

	public function testUnsetValueFromNonExistentSimpleField(): void
	{
		$field = Field::create('missing');
		$values = ['name' => 'John'];
		
		$field->unsetValueFrom($values);
		
		self::assertSame(['name' => 'John'], $values);
	}

	public function testUnsetValueFromNonExistentNestedField(): void
	{
		$field = Field::create('user.missing');
		$values = [
			'user' => ['name' => 'John'],
		];
		
		$field->unsetValueFrom($values);
		
		self::assertSame(['user' => ['name' => 'John']], $values);
	}

	public function testUnsetValueFromNonExistentParentPath(): void
	{
		$field = Field::create('missing.name');
		$values = [
			'user' => ['name' => 'John'],
		];
		
		$field->unsetValueFrom($values);
		
		self::assertSame(['user' => ['name' => 'John']], $values);
	}

	public function testUnsetValueFromParentIsNotArray(): void
	{
		$field = Field::create('user.name');
		$values = [
			'user' => 'not an array',
		];
		
		$field->unsetValueFrom($values);
		
		self::assertSame(['user' => 'not an array'], $values);
	}

	public function testUnsetValueFromIntermediateParentIsNotArray(): void
	{
		$field = Field::create('user.profile.name');
		$values = [
			'user' => [
				'profile' => 'not an array',
				'age' => 30
			]
		];
		
		$field->unsetValueFrom($values);
		
		self::assertSame([
			'user' => [
				'profile' => 'not an array',
				'age' => 30
			]
		], $values);
	}

	public function testUnsetValueFromEmptyArray(): void
	{
		$field = Field::create('name');
		$values = [];
		
		$field->unsetValueFrom($values);
		
		self::assertSame([], $values);
	}

}

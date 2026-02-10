<?php declare(strict_types = 1);

namespace Tests\Unit;

use Shredio\RequestMapper\Attribute\RequestParam;
use Shredio\RequestMapper\Request\RequestLocation;
use Shredio\RequestMapper\RequestMapperConfiguration;
use Shredio\RequestMapper\Symfony\SymfonyRequestContextFactory;
use Shredio\TypeSchema\TypeSchema;
use Tests\Fixtures\IntBackedStatus;
use Tests\Fixtures\Status;
use Tests\RequestMapperTestCase;

final class RequestMapperArrayTest extends RequestMapperTestCase
{

	public function testMapRouteParameterToInt(): void
	{
		$request = $this->createRequest(path: ['id' => '123']);
		$context = $this->createContextForSimpleArticleInput($request);

		$type = TypeSchema::get()->arrayShape([
			'id' => TypeSchema::get()->int(),
			'category' => TypeSchema::get()->optional(TypeSchema::get()->string()),
			'published' => TypeSchema::get()->optional(TypeSchema::get()->bool()),
		]);

		$result = $this->mapper->mapToArray($type, $context);

		self::assertSame(123, $result['id']);
		self::assertArrayNotHasKey('category', $result);
		self::assertArrayNotHasKey('published', $result);
	}

	public function testMapQueryParametersOverrideDefaults(): void
	{
		$request = $this->createRequest(query: ['category' => 'tech', 'published' => '0'], path: ['id' => '456']);
		$context = $this->createContextForSimpleArticleInput($request);

		$type = TypeSchema::get()->arrayShape([
			'id' => TypeSchema::get()->int(),
			'category' => TypeSchema::get()->optional(TypeSchema::get()->string()),
			'published' => TypeSchema::get()->optional(TypeSchema::get()->bool()),
		]);

		$result = $this->mapper->mapToArray($type, $context);

		self::assertSame(456, $result['id']);
		self::assertSame('tech', $result['category']);
		self::assertFalse($result['published']);
	}

	public function testMapStringBackedEnumFromQuery(): void
	{
		$request = $this->createRequest(query: ['status' => 'draft']);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$type = TypeSchema::get()->arrayShape([
			'status' => TypeSchema::get()->mapper(Status::class),
		]);

		$result = $this->mapper->mapToArray($type, $context);

		self::assertSame('draft', $result['status']->value);
	}

	public function testRejectInvalidEnumValue(): void
	{
		$request = $this->createRequest(query: ['status' => 'drafts']);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$type = TypeSchema::get()->arrayShape([
			'status' => TypeSchema::get()->mapper(Status::class),
		]);

		$this->expectInvalidRequest([
			'status' => 'The value you selected is not a valid choice.',
		]);
		$this->mapper->mapToArray($type, $context);
	}

	public function testMapBodyParametersWithExplicitBodyLocation(): void
	{
		$request = $this->createRequest('POST', body: [
			'title' => 'Test Article',
			'content' => 'This is test content',
		]);
		$context = SymfonyRequestContextFactory::createFrom($request, new RequestMapperConfiguration(location: RequestLocation::Body));

		$type = TypeSchema::get()->arrayShape([
			'title' => TypeSchema::get()->string(),
			'content' => TypeSchema::get()->optional(TypeSchema::get()->string()),
		]);

		$result = $this->mapper->mapToArray($type, $context);

		self::assertSame('Test Article', $result['title']);
		self::assertSame('This is test content', $result['content']);
	}

	public function testMapWithSourceKeyMapping(): void
	{
		$request = $this->createRequest(query: ['filter' => 'active'], path: ['userId' => '100'], headers: ['X-API-Key' => 'secret123']);
		$context = SymfonyRequestContextFactory::createFrom($request, new RequestMapperConfiguration([
			'id' => new RequestParam(sourceKey: 'userId', location: RequestLocation::Route),
			'apiKey' => new RequestParam(sourceKey: 'X-API-Key', location: RequestLocation::Header),
		]));

		$type = TypeSchema::get()->arrayShape([
			'id' => TypeSchema::get()->int(),
			'filter' => TypeSchema::get()->optional(TypeSchema::get()->string()),
			'apiKey' => TypeSchema::get()->optional(TypeSchema::get()->string()),
		]);

		$result = $this->mapper->mapToArray($type, $context);

		self::assertSame(100, $result['id']);
		self::assertSame('active', $result['filter']);
		self::assertSame('secret123', $result['apiKey']);
	}

	public function testRejectSingleUnexpectedField(): void
	{
		$request = $this->createRequest('POST', body: [
			'id' => 1,
			'extraOne' => 'value1',
		]);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$type = TypeSchema::get()->arrayShape([
			'id' => TypeSchema::get()->int(),
			'filter' => TypeSchema::get()->optional(TypeSchema::get()->string()),
			'apiKey' => TypeSchema::get()->optional(TypeSchema::get()->string()),
		]);

		$this->expectInvalidRequest([
			'extraOne' => 'This field was not expected.',
		]);
		$this->mapper->mapToArray($type, $context);
	}

	public function testRejectTwoUnexpectedFields(): void
	{
		$request = $this->createRequest('POST', body: [
			'id' => 1,
			'extraOne' => 'value1',
			'extraTwo' => 'value2',
		]);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$type = TypeSchema::get()->arrayShape([
			'id' => TypeSchema::get()->int(),
			'filter' => TypeSchema::get()->optional(TypeSchema::get()->string()),
			'apiKey' => TypeSchema::get()->optional(TypeSchema::get()->string()),
		]);

		$this->expectInvalidRequest([
			'extraOne' => 'This field was not expected.',
			'extraTwo' => 'This field was not expected.',
		]);
		$this->mapper->mapToArray($type, $context);
	}

	public function testRejectThreeUnexpectedFields(): void
	{
		$request = $this->createRequest('POST', body: [
			'id' => 1,
			'extraOne' => 'value1',
			'extraTwo' => 'value2',
			'extraThree' => 'value3',
		]);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$type = TypeSchema::get()->arrayShape([
			'id' => TypeSchema::get()->int(),
			'filter' => TypeSchema::get()->optional(TypeSchema::get()->string()),
			'apiKey' => TypeSchema::get()->optional(TypeSchema::get()->string()),
		]);

		$this->expectInvalidRequest([
			'extraOne' => 'This field was not expected.',
			'extraTwo' => 'This field was not expected.',
			'extraThree' => 'This field was not expected.',
		]);
		$this->mapper->mapToArray($type, $context);
	}

	public function testRejectMissingRequiredFieldWithSourceKey(): void
	{
		$request = $this->createRequest();
		$context = SymfonyRequestContextFactory::createFrom($request, new RequestMapperConfiguration([
			'id' => new RequestParam(sourceKey: 'userId', location: RequestLocation::Route),
		]));

		$type = TypeSchema::get()->arrayShape([
			'id' => TypeSchema::get()->int(),
			'filter' => TypeSchema::get()->optional(TypeSchema::get()->string()),
			'apiKey' => TypeSchema::get()->optional(TypeSchema::get()->string()),
		]);

		$this->expectInvalidRequest([
			'userId' => 'This field is missing.',
		]);
		$this->mapper->mapToArray($type, $context);
	}

	public function testMapQueryParametersForGetRequest(): void
	{
		$request = $this->createRequest(query: ['name' => 'John', 'age' => '30']);
		$context = SymfonyRequestContextFactory::createFrom($request, new RequestMapperConfiguration([
			'age' => new RequestParam(),
		]));

		$type = TypeSchema::get()->arrayShape([
			'name' => TypeSchema::get()->string(),
			'age' => TypeSchema::get()->optional(TypeSchema::get()->int()),
		]);

		$result = $this->mapper->mapToArray($type, $context);

		self::assertSame('John', $result['name']);
		self::assertSame(30, $result['age']);
	}

	public function testMapBodyParametersForPostRequest(): void
	{
		$request = $this->createRequest('POST', body: ['name' => 'John', 'age' => 30]);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$type = TypeSchema::get()->arrayShape([
			'name' => TypeSchema::get()->string(),
			'age' => TypeSchema::get()->optional(TypeSchema::get()->int()),
		]);

		$result = $this->mapper->mapToArray($type, $context);

		self::assertSame('John', $result['name']);
		self::assertSame(30, $result['age']);
	}

	public function testRejectMissingRequiredField(): void
	{
		$request = $this->createRequest('POST', body: []);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$type = TypeSchema::get()->arrayShape([
			'id' => TypeSchema::get()->int(),
			'title' => TypeSchema::get()->string(),
			'content' => TypeSchema::get()->string(),
			'category' => TypeSchema::get()->optional(TypeSchema::get()->string()),
			'published' => TypeSchema::get()->optional(TypeSchema::get()->bool()),
		]);

		$this->expectInvalidRequest([
			'id' => 'This field is missing.',
		]);
		$this->mapper->mapToArray($type, $context);
	}

	public function testMapWithSinglePresetValue(): void
	{
		$request = $this->createRequest('POST', body: [
			'id' => 1,
			'title' => 'Test Article',
		]);
		$context = SymfonyRequestContextFactory::createFrom($request, new RequestMapperConfiguration(presetValues: ['content' => 'text']));

		$type = TypeSchema::get()->arrayShape([
			'id' => TypeSchema::get()->int(),
			'title' => TypeSchema::get()->string(),
			'content' => TypeSchema::get()->string(),
			'category' => TypeSchema::get()->optional(TypeSchema::get()->string()),
			'published' => TypeSchema::get()->optional(TypeSchema::get()->bool()),
		]);

		$result = $this->mapper->mapToArray($type, $context);

		self::assertSame('text', $result['content']);
	}

	public function testRejectUserProvidedValueForPresetField(): void
	{
		$request = $this->createRequest('POST', body: [
			'id' => 1,
			'title' => 'Test Article',
			'content' => 'This should be overridden',
		]);
		$context = SymfonyRequestContextFactory::createFrom($request, new RequestMapperConfiguration(presetValues: ['content' => 'text']));

		$type = TypeSchema::get()->arrayShape([
			'id' => TypeSchema::get()->int(),
			'title' => TypeSchema::get()->string(),
			'content' => TypeSchema::get()->string(),
			'category' => TypeSchema::get()->optional(TypeSchema::get()->string()),
			'published' => TypeSchema::get()->optional(TypeSchema::get()->bool()),
		]);

		$this->expectInvalidRequest([
			'content' => 'This field was not expected.',
		]);
		$this->mapper->mapToArray($type, $context);
	}

	public function testMapWithMultiplePresetValues(): void
	{
		$request = $this->createRequest('POST', body: [
			'id' => 1,
		]);
		$context = SymfonyRequestContextFactory::createFrom($request, new RequestMapperConfiguration(presetValues: ['title' => 'Title text', 'content' => 'text']));

		$type = TypeSchema::get()->arrayShape([
			'id' => TypeSchema::get()->int(),
			'title' => TypeSchema::get()->string(),
			'content' => TypeSchema::get()->string(),
			'category' => TypeSchema::get()->optional(TypeSchema::get()->string()),
			'published' => TypeSchema::get()->optional(TypeSchema::get()->bool()),
		]);

		$result = $this->mapper->mapToArray($type, $context);

		self::assertSame('text', $result['content']);
		self::assertSame('Title text', $result['title']);
	}

	public function testRejectPresetValueWithInvalidType(): void
	{
		$request = $this->createRequest('POST', body: [
			'id' => 1,
			'title' => 'Test Article',
		]);
		$context = SymfonyRequestContextFactory::createFrom($request, new RequestMapperConfiguration(presetValues: ['content' => 42]));

		$type = TypeSchema::get()->arrayShape([
			'id' => TypeSchema::get()->int(),
			'title' => TypeSchema::get()->string(),
			'content' => TypeSchema::get()->string(),
			'category' => TypeSchema::get()->optional(TypeSchema::get()->string()),
			'published' => TypeSchema::get()->optional(TypeSchema::get()->bool()),
		]);

		$this->expectInvalidPresetValues([
			'content' => 'Invalid type int with value 42, expected string.',
		]);
		$this->mapper->mapToArray($type, $context);
	}

	public function testRejectMultiplePresetValuesWithInvalidType(): void
	{
		$request = $this->createRequest('POST', body: [
			'id' => 1,
			'title' => 'Test Article',
		]);
		$context = SymfonyRequestContextFactory::createFrom($request, new RequestMapperConfiguration(presetValues: ['title' => 42, 'content' => 42]));

		$type = TypeSchema::get()->arrayShape([
			'id' => TypeSchema::get()->int(),
			'title' => TypeSchema::get()->string(),
			'content' => TypeSchema::get()->string(),
			'category' => TypeSchema::get()->optional(TypeSchema::get()->string()),
			'published' => TypeSchema::get()->optional(TypeSchema::get()->bool()),
		]);

		$this->expectInvalidPresetValues([
			'title' => 'Invalid type int with value 42, expected string.',
			'content' => 'Invalid type int with value 42, expected string.',
		]);
		$this->mapper->mapToArray($type, $context);
	}

	public function testMapIntBackedEnumFromQuery(): void
	{
		$request = $this->createRequest(query: ['status' => '1']);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$type = TypeSchema::get()->arrayShape([
			'status' => TypeSchema::get()->mapper(IntBackedStatus::class),
		]);

		$result = $this->mapper->mapToArray($type, $context);

		self::assertSame(1, $result['status']->value);
	}

	public function testRejectFloatStringForIntParameter(): void
	{
		$request = $this->createRequest(query: ['name' => 'John', 'age' => '30.5']);
		$context = SymfonyRequestContextFactory::createFrom($request, new RequestMapperConfiguration([
			'age' => new RequestParam(),
		]));

		$type = TypeSchema::get()->arrayShape([
			'name' => TypeSchema::get()->string(),
			'age' => TypeSchema::get()->optional(TypeSchema::get()->int()),
		]);

		$this->expectInvalidRequest([
			'age' => 'This value is not valid.',
		]);
		$this->mapper->mapToArray($type, $context);
	}

	public function testMapBooleanFalseFromBody(): void
	{
		$request = $this->createRequest('POST', body: [
			'id' => 1,
			'title' => 'Test',
			'content' => 'Text',
			'published' => false,
		]);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$type = TypeSchema::get()->arrayShape([
			'id' => TypeSchema::get()->int(),
			'title' => TypeSchema::get()->string(),
			'content' => TypeSchema::get()->string(),
			'category' => TypeSchema::get()->optional(TypeSchema::get()->string()),
			'published' => TypeSchema::get()->optional(TypeSchema::get()->bool()),
		]);

		$result = $this->mapper->mapToArray($type, $context);

		self::assertFalse($result['published']);
	}

	public function testMapFromAttributeLocation(): void
	{
		$request = $this->createRequest(query: ['filter' => 'active']);
		$request->attributes->set('userId', '100');
		$context = SymfonyRequestContextFactory::createFrom($request, new RequestMapperConfiguration([
			'id' => new RequestParam(sourceKey: 'userId', location: RequestLocation::Attribute),
		]));

		$type = TypeSchema::get()->arrayShape([
			'id' => TypeSchema::get()->int(),
			'filter' => TypeSchema::get()->optional(TypeSchema::get()->string()),
			'apiKey' => TypeSchema::get()->optional(TypeSchema::get()->string()),
		]);

		$result = $this->mapper->mapToArray($type, $context);

		self::assertSame(100, $result['id']);
		self::assertSame('active', $result['filter']);
	}

	public function testMapFromServerLocation(): void
	{
		$request = $this->createRequest(query: ['name' => 'John']);
		$request->server->set('CUSTOM_PORT', '8080');
		$context = SymfonyRequestContextFactory::createFrom($request, new RequestMapperConfiguration([
			'age' => new RequestParam(sourceKey: 'CUSTOM_PORT', location: RequestLocation::Server),
		]));

		$type = TypeSchema::get()->arrayShape([
			'name' => TypeSchema::get()->string(),
			'age' => TypeSchema::get()->optional(TypeSchema::get()->int()),
		]);

		$result = $this->mapper->mapToArray($type, $context);

		self::assertSame('John', $result['name']);
		self::assertSame(8080, $result['age']);
	}

	public function testMapHeaderWithCaseInsensitiveKey(): void
	{
		$request = $this->createRequest(query: ['filter' => 'active'], path: ['userId' => '100'], headers: ['x-api-key' => 'secret123']);
		$context = SymfonyRequestContextFactory::createFrom($request, new RequestMapperConfiguration([
			'id' => new RequestParam(sourceKey: 'userId', location: RequestLocation::Route),
			'apiKey' => new RequestParam(sourceKey: 'X-API-Key', location: RequestLocation::Header),
		]));

		$type = TypeSchema::get()->arrayShape([
			'id' => TypeSchema::get()->int(),
			'filter' => TypeSchema::get()->optional(TypeSchema::get()->string()),
			'apiKey' => TypeSchema::get()->optional(TypeSchema::get()->string()),
		]);

		$result = $this->mapper->mapToArray($type, $context);

		self::assertSame('secret123', $result['apiKey']);
	}

	public function testMapWithLocationAsParamConfig(): void
	{
		$request = $this->createRequest(path: ['id' => '123']);
		$context = SymfonyRequestContextFactory::createFrom($request, new RequestMapperConfiguration([
			'id' => RequestLocation::Route,
		]));

		$type = TypeSchema::get()->arrayShape([
			'id' => TypeSchema::get()->int(),
			'category' => TypeSchema::get()->optional(TypeSchema::get()->string()),
			'published' => TypeSchema::get()->optional(TypeSchema::get()->bool()),
		]);

		$result = $this->mapper->mapToArray($type, $context);

		self::assertSame(123, $result['id']);
	}

	public function testMapWithDefaultBodyLocationForPostRequest(): void
	{
		$request = $this->createRequest('POST', query: ['name' => 'FromQuery'], body: ['name' => 'FromBody', 'age' => 30]);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$type = TypeSchema::get()->arrayShape([
			'name' => TypeSchema::get()->string(),
			'age' => TypeSchema::get()->optional(TypeSchema::get()->int()),
		]);

		$result = $this->mapper->mapToArray($type, $context);

		self::assertSame('FromBody', $result['name']);
	}

	public function testPresetValueForOptionalField(): void
	{
		$request = $this->createRequest('POST', body: [
			'id' => 1,
			'title' => 'Test Article',
			'content' => 'Text',
		]);
		$context = SymfonyRequestContextFactory::createFrom($request, new RequestMapperConfiguration(presetValues: ['category' => 'preset']));

		$type = TypeSchema::get()->arrayShape([
			'id' => TypeSchema::get()->int(),
			'title' => TypeSchema::get()->string(),
			'content' => TypeSchema::get()->string(),
			'category' => TypeSchema::get()->optional(TypeSchema::get()->string()),
			'published' => TypeSchema::get()->optional(TypeSchema::get()->bool()),
		]);

		$result = $this->mapper->mapToArray($type, $context);

		self::assertSame('preset', $result['category']);
	}

	public function testRejectMissingMultipleRequiredFields(): void
	{
		$request = $this->createRequest('POST', body: []);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$type = TypeSchema::get()->arrayShape([
			'id' => TypeSchema::get()->int(),
			'title' => TypeSchema::get()->string(),
			'content' => TypeSchema::get()->string(),
			'category' => TypeSchema::get()->optional(TypeSchema::get()->string()),
			'published' => TypeSchema::get()->optional(TypeSchema::get()->bool()),
		]);

		$this->expectInvalidRequest([
			'id' => 'This field is missing.',
			'title' => 'This field is missing.',
			'content' => 'This field is missing.',
		]);
		$this->mapper->mapToArray($type, $context);
	}

	public function testRejectInvalidTypeInBody(): void
	{
		$request = $this->createRequest('POST', body: [
			'id' => 'not-a-number',
			'title' => 'Test',
			'content' => 'Text',
		]);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$type = TypeSchema::get()->arrayShape([
			'id' => TypeSchema::get()->int(),
			'title' => TypeSchema::get()->string(),
			'content' => TypeSchema::get()->string(),
			'category' => TypeSchema::get()->optional(TypeSchema::get()->string()),
			'published' => TypeSchema::get()->optional(TypeSchema::get()->bool()),
		]);

		$this->expectInvalidRequest([
			'id' => 'This value is not valid.',
		]);
		$this->mapper->mapToArray($type, $context);
	}

}
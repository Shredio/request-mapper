<?php declare(strict_types = 1);

namespace Tests\Unit;

use Shredio\RequestMapper\Attribute\RequestParam;
use Shredio\RequestMapper\Exception\LogicException;
use Shredio\RequestMapper\Request\Exception\InvalidRequestException;
use Shredio\RequestMapper\Request\RequestContext;
use Shredio\RequestMapper\Request\RequestLocation;
use Shredio\RequestMapper\RequestMapperConfiguration;
use Shredio\RequestMapper\Symfony\SymfonyRequestContextFactory;
use Symfony\Component\HttpFoundation\Request;
use Tests\Fixtures\ArticleInput;
use Tests\Fixtures\EnumInput;
use Tests\Fixtures\IntBackedEnumInput;
use Tests\Fixtures\SimpleArticleInput;
use Tests\Fixtures\SimpleBodyInput;
use Tests\Fixtures\SimpleInput;
use Tests\Fixtures\UserInput;
use Tests\Fixtures\ValueMapperInput;
use Tests\MapperTestCase;
use Tests\RequestMapperTestCase;

final class RequestMapperTest extends RequestMapperTestCase
{

	public function testMapRouteParameterToInt(): void
	{
		$request = $this->createRequest(path: ['id' => '123']);
		$context = $this->createContextForSimpleArticleInput($request);

		$result = $this->mapper->mapToObject(SimpleArticleInput::class, $context);

		self::assertInstanceOf(SimpleArticleInput::class, $result);
		self::assertSame(123, $result->id);
		self::assertSame('general', $result->category);
		self::assertTrue($result->published);
	}

	public function testMapQueryParametersOverrideDefaults(): void
	{
		$request = $this->createRequest(query: ['category' => 'tech', 'published' => '0'], path: ['id' => '456']);
		$context = $this->createContextForSimpleArticleInput($request);

		$result = $this->mapper->mapToObject(SimpleArticleInput::class, $context);

		self::assertInstanceOf(SimpleArticleInput::class, $result);
		self::assertSame(456, $result->id);
		self::assertSame('tech', $result->category);
		self::assertFalse($result->published);
	}

	public function testMapStringBackedEnumFromQuery(): void
	{
		$request = $this->createRequest(query: ['status' => 'draft']);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$result = $this->mapper->mapToObject(EnumInput::class, $context);

		self::assertInstanceOf(EnumInput::class, $result);
		self::assertSame('draft', $result->status->value);
	}

	public function testMapValueObjectsFromBody(): void
	{
		$request = $this->createRequest('POST', body: ['stringObject' => 'foo', 'intObject' => 42]);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$result = $this->mapper->mapToObject(ValueMapperInput::class, $context);

		self::assertSame('foo', $result->stringObject->value);
		self::assertSame(42, $result->intObject->value);
	}

	public function testRejectInvalidValueObjectTypeInBody(): void
	{
		$request = $this->createRequest('POST', body: ['stringObject' => 42, 'intObject' => 'foo']);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$this->expectInvalidRequest([
			'stringObject' => 'This value is not valid.',
		]);

		$this->mapper->mapToObject(ValueMapperInput::class, $context);
	}

	public function testMapValueObjectsFromQuery(): void
	{
		$request = $this->createRequest(query: ['stringObject' => 'foo', 'intObject' => '42']);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$result = $this->mapper->mapToObject(ValueMapperInput::class, $context);

		self::assertSame('foo', $result->stringObject->value);
		self::assertSame(42, $result->intObject->value);
	}

	public function testRejectNonStringValueInQuery(): void
	{
		$request = $this->createRequest(query: ['stringObject' => 42, 'intObject' => '12']);
		$context = SymfonyRequestContextFactory::createFrom($request);

		// Query should be always only strings
		$this->expectInvalidRequest([
			'stringObject' => 'This value is not valid.',
		]);

		$this->mapper->mapToObject(ValueMapperInput::class, $context);
	}

	public function testRejectInvalidValueObjectTypeInQuery(): void
	{
		$request = $this->createRequest(query: ['stringObject' => '42', 'intObject' => '12.4']);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$this->expectInvalidRequest([
			'intObject' => 'This value is not valid.',
		]);

		$this->mapper->mapToObject(ValueMapperInput::class, $context);
	}

	public function testMapValueObjectsWithExplicitQueryLocation(): void
	{
		$request = $this->createRequest(query: ['stringObject' => '42', 'intObject' => '12']);
		$context = SymfonyRequestContextFactory::createFrom($request, new RequestMapperConfiguration([
			'stringObject' => new RequestParam(location: RequestLocation::Query),
			'intObject' => new RequestParam(location: RequestLocation::Query),
		]));

		$result = $this->mapper->mapToObject(ValueMapperInput::class, $context);

		self::assertSame('42', $result->stringObject->value);
		self::assertSame(12, $result->intObject->value);
	}

	public function testRejectInvalidEnumValue(): void
	{
		$request = $this->createRequest(query: ['status' => 'drafts']);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$this->expectInvalidRequest([
			'status' => 'The value you selected is not a valid choice.',
		]);
		$this->mapper->mapToObject(EnumInput::class, $context);
	}

	public function testMapBodyParametersWithExplicitBodyLocation(): void
	{
		$request = $this->createRequest('POST', body: [
			'title' => 'Test Article',
			'content' => 'This is test content',
		]);
		$context = SymfonyRequestContextFactory::createFrom($request, new RequestMapperConfiguration(location: RequestLocation::Body));

		$result = $this->mapper->mapToObject(SimpleBodyInput::class, $context);

		self::assertInstanceOf(SimpleBodyInput::class, $result);
		self::assertSame('Test Article', $result->title);
		self::assertSame('This is test content', $result->content);
	}

	public function testMapWithSourceKeyMapping(): void
	{
		$request = $this->createRequest(query: ['filter' => 'active'], path: ['userId' => '100'], headers: ['X-API-Key' => 'secret123']);
		$context = SymfonyRequestContextFactory::createFrom($request, new RequestMapperConfiguration([
			'id' => new RequestParam(sourceKey: 'userId', location: RequestLocation::Route),
			'apiKey' => new RequestParam(sourceKey: 'X-API-Key', location: RequestLocation::Header),
		]));

		$result = $this->mapper->mapToObject(UserInput::class, $context);

		self::assertInstanceOf(UserInput::class, $result);
		self::assertSame(100, $result->id);
		self::assertSame('active', $result->filter);
		self::assertSame('secret123', $result->apiKey);
	}

	public function testRejectSingleUnexpectedField(): void
	{
		$request = $this->createRequest('POST', body: [
			'id' => 1,
			'extraOne' => 'value1',
		]);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$this->expectInvalidRequest([
			'extraOne' => 'This field was not expected.',
		]);
		$this->mapper->mapToObject(UserInput::class, $context);
	}

	public function testRejectTwoUnexpectedFields(): void
	{
		$request = $this->createRequest('POST', body: [
			'id' => 1,
			'extraOne' => 'value1',
			'extraTwo' => 'value2',
		]);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$this->expectInvalidRequest([
			'extraOne' => 'This field was not expected.',
			'extraTwo' => 'This field was not expected.',
		]);
		$this->mapper->mapToObject(UserInput::class, $context);
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

		$this->expectInvalidRequest([
			'extraOne' => 'This field was not expected.',
			'extraTwo' => 'This field was not expected.',
			'extraThree' => 'This field was not expected.',
		]);
		$this->mapper->mapToObject(UserInput::class, $context);
	}

	public function testRejectMissingRequiredFieldWithSourceKey(): void
	{
		$request = $this->createRequest();
		$context = SymfonyRequestContextFactory::createFrom($request, new RequestMapperConfiguration([
			'id' => new RequestParam(sourceKey: 'userId', location: RequestLocation::Route),
		]));

		$this->expectInvalidRequest([
			'userId' => 'This field is missing.',
		]);
		$this->mapper->mapToObject(UserInput::class, $context);
	}

	public function testMapQueryParametersForGetRequest(): void
	{
		$request = $this->createRequest(query: ['name' => 'John', 'age' => '30']);
		$context = SymfonyRequestContextFactory::createFrom($request, new RequestMapperConfiguration([
			'age' => new RequestParam(),
		]));

		$result = $this->mapper->mapToObject(SimpleInput::class, $context);

		self::assertInstanceOf(SimpleInput::class, $result);
		self::assertSame('John', $result->name);
		self::assertSame(30, $result->age);
	}

	public function testMapBodyParametersForPostRequest(): void
	{
		$request = $this->createRequest('POST', body: ['name' => 'John', 'age' => 30]);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$result = $this->mapper->mapToObject(SimpleInput::class, $context);

		self::assertInstanceOf(SimpleInput::class, $result);
		self::assertSame('John', $result->name);
		self::assertSame(30, $result->age);
	}

	public function testRejectMissingRequiredField(): void
	{
		$request = $this->createRequest('POST', body: []);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$this->expectInvalidRequest([
			'id' => 'This field is missing.',
		]);
		$this->mapper->mapToObject(ArticleInput::class, $context);
	}

	public function testMapWithSinglePresetValue(): void
	{
		$request = $this->createRequest('POST', body: [
			'id' => 1,
			'title' => 'Test Article',
		]);
		$context = SymfonyRequestContextFactory::createFrom($request, new RequestMapperConfiguration(presetValues: ['content' => 'text']));

		$input = $this->mapper->mapToObject(ArticleInput::class, $context);

		self::assertSame('text', $input->content);
	}

	public function testRejectUserProvidedValueForPresetField(): void
	{
		$request = $this->createRequest('POST', body: [
			'id' => 1,
			'title' => 'Test Article',
			'content' => 'This should be overridden',
		]);
		$context = SymfonyRequestContextFactory::createFrom($request, new RequestMapperConfiguration(presetValues: ['content' => 'text']));

		$this->expectInvalidRequest([
			'content' => 'This field was not expected.',
		]);
		$this->mapper->mapToObject(ArticleInput::class, $context);
	}

	public function testMapWithMultiplePresetValues(): void
	{
		$request = $this->createRequest('POST', body: [
			'id' => 1,
		]);
		$context = SymfonyRequestContextFactory::createFrom($request, new RequestMapperConfiguration(presetValues: ['title' => 'Title text', 'content' => 'text']));

		$input = $this->mapper->mapToObject(ArticleInput::class, $context);

		self::assertSame('text', $input->content);
		self::assertSame('Title text', $input->title);
	}

	public function testRejectPresetValueWithInvalidType(): void
	{
		$request = $this->createRequest('POST', body: [
			'id' => 1,
			'title' => 'Test Article',
		]);
		$context = SymfonyRequestContextFactory::createFrom($request, new RequestMapperConfiguration(presetValues: ['content' => 42]));

		$this->expectInvalidPresetValues([
			'content' => 'Invalid type int with value 42, expected string.',
		]);
		$this->mapper->mapToObject(ArticleInput::class, $context);
	}

	public function testRejectMultiplePresetValuesWithInvalidType(): void
	{
		$request = $this->createRequest('POST', body: [
			'id' => 1,
			'title' => 'Test Article',
		]);
		$context = SymfonyRequestContextFactory::createFrom($request, new RequestMapperConfiguration(presetValues: ['title' => 42, 'content' => 42]));

		$this->expectInvalidPresetValues([
			'title' => 'Invalid type int with value 42, expected string.',
			'content' => 'Invalid type int with value 42, expected string.',
		]);
		$this->mapper->mapToObject(ArticleInput::class, $context);
	}

	public function testMapIntBackedEnumFromQuery(): void
	{
		$request = $this->createRequest(query: ['status' => '1']);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$result = $this->mapper->mapToObject(IntBackedEnumInput::class, $context);

		self::assertInstanceOf(IntBackedEnumInput::class, $result);
		self::assertSame(1, $result->status->value);
	}

	public function testRejectFloatStringForIntParameter(): void
	{
		$request = $this->createRequest(query: ['name' => 'John', 'age' => '30.5']);
		$context = SymfonyRequestContextFactory::createFrom($request, new RequestMapperConfiguration([
			'age' => new RequestParam(),
		]));

		$this->expectInvalidRequest([
			'age' => 'This value is not valid.',
		]);
		$this->mapper->mapToObject(SimpleInput::class, $context);
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

		$result = $this->mapper->mapToObject(ArticleInput::class, $context);

		self::assertFalse($result->published);
	}

	public function testMapFromAttributeLocation(): void
	{
		$request = $this->createRequest(query: ['filter' => 'active']);
		$request->attributes->set('userId', '100');
		$context = SymfonyRequestContextFactory::createFrom($request, new RequestMapperConfiguration([
			'id' => new RequestParam(sourceKey: 'userId', location: RequestLocation::Attribute),
		]));

		$result = $this->mapper->mapToObject(UserInput::class, $context);

		self::assertSame(100, $result->id);
		self::assertSame('active', $result->filter);
	}

	public function testMapFromServerLocation(): void
	{
		$request = $this->createRequest(query: ['name' => 'John']);
		$request->server->set('CUSTOM_PORT', '8080');
		$context = SymfonyRequestContextFactory::createFrom($request, new RequestMapperConfiguration([
			'age' => new RequestParam(sourceKey: 'CUSTOM_PORT', location: RequestLocation::Server),
		]));

		$result = $this->mapper->mapToObject(SimpleInput::class, $context);

		self::assertSame('John', $result->name);
		self::assertSame(8080, $result->age);
	}

	public function testMapHeaderWithCaseInsensitiveKey(): void
	{
		$request = $this->createRequest(query: ['filter' => 'active'], path: ['userId' => '100'], headers: ['x-api-key' => 'secret123']);
		$context = SymfonyRequestContextFactory::createFrom($request, new RequestMapperConfiguration([
			'id' => new RequestParam(sourceKey: 'userId', location: RequestLocation::Route),
			'apiKey' => new RequestParam(sourceKey: 'X-API-Key', location: RequestLocation::Header),
		]));

		$result = $this->mapper->mapToObject(UserInput::class, $context);

		self::assertSame('secret123', $result->apiKey);
	}

	public function testMapWithLocationAsParamConfig(): void
	{
		$request = $this->createRequest(path: ['id' => '123']);
		$context = SymfonyRequestContextFactory::createFrom($request, new RequestMapperConfiguration([
			'id' => RequestLocation::Route,
		]));

		$result = $this->mapper->mapToObject(SimpleArticleInput::class, $context);

		self::assertSame(123, $result->id);
	}

	public function testMapWithDefaultBodyLocationForPostRequest(): void
	{
		$request = $this->createRequest('POST', query: ['name' => 'FromQuery'], body: ['name' => 'FromBody', 'age' => 30]);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$result = $this->mapper->mapToObject(SimpleInput::class, $context);

		self::assertSame('FromBody', $result->name);
	}

	public function testPresetValueForOptionalField(): void
	{
		$request = $this->createRequest('POST', body: [
			'id' => 1,
			'title' => 'Test Article',
			'content' => 'Text',
		]);
		$context = SymfonyRequestContextFactory::createFrom($request, new RequestMapperConfiguration(presetValues: ['category' => 'preset']));

		$input = $this->mapper->mapToObject(ArticleInput::class, $context);

		self::assertSame('preset', $input->category);
	}

	public function testRejectMissingMultipleRequiredFields(): void
	{
		$request = $this->createRequest('POST', body: []);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$this->expectInvalidRequest([
			'id' => 'This field is missing.',
			'title' => 'This field is missing.',
			'content' => 'This field is missing.',
		]);
		$this->mapper->mapToObject(ArticleInput::class, $context);
	}

	public function testRejectInvalidTypeInBody(): void
	{
		$request = $this->createRequest('POST', body: [
			'id' => 'not-a-number',
			'title' => 'Test',
			'content' => 'Text',
		]);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$this->expectInvalidRequest([
			'id' => 'This value is not valid.',
		]);
		$this->mapper->mapToObject(ArticleInput::class, $context);
	}

}

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
use Tests\Fixtures\SimpleArticleInput;
use Tests\Fixtures\SimpleBodyInput;
use Tests\Fixtures\SimpleInput;
use Tests\Fixtures\UserInput;
use Tests\Fixtures\ValueMapperInput;
use Tests\MapperTestCase;
use Tests\RequestMapperTestCase;

final class RequestMapperTest extends RequestMapperTestCase
{

	public function testMapFromPathParameters(): void
	{
		$request = $this->createRequest(path: ['id' => '123']);
		$context = $this->createContextForSimpleArticleInput($request);

		$result = $this->mapper->mapToObject(SimpleArticleInput::class, $context);

		self::assertInstanceOf(SimpleArticleInput::class, $result);
		self::assertSame(123, $result->id);
		self::assertSame('general', $result->category);
		self::assertTrue($result->published);
	}

	public function testMapFromQueryParameters(): void
	{
		$request = $this->createRequest(query: ['category' => 'tech', 'published' => '0'], path: ['id' => '456']);
		$context = $this->createContextForSimpleArticleInput($request);

		$result = $this->mapper->mapToObject(SimpleArticleInput::class, $context);

		self::assertInstanceOf(SimpleArticleInput::class, $result);
		self::assertSame(456, $result->id);
		self::assertSame('tech', $result->category);
		self::assertFalse($result->published);
	}

	public function testEnumType(): void
	{
		$request = $this->createRequest(query: ['status' => 'draft']);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$result = $this->mapper->mapToObject(EnumInput::class, $context);

		self::assertInstanceOf(EnumInput::class, $result);
		self::assertSame('draft', $result->status->value);
	}

	public function testValueObjectsValidInBody(): void
	{
		$request = $this->createRequest('POST', body: ['stringObject' => 'foo', 'intObject' => 42]);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$result = $this->mapper->mapToObject(ValueMapperInput::class, $context);

		self::assertSame('foo', $result->stringObject->value);
		self::assertSame(42, $result->intObject->value);
	}

	public function testValueObjectsInvalidTypesInBody(): void
	{
		$request = $this->createRequest('POST', body: ['stringObject' => 42, 'intObject' => 'foo']);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$this->expectInvalidRequest([
			'stringObject' => 'This value is not valid.',
		]);

		$this->mapper->mapToObject(ValueMapperInput::class, $context);
	}

	public function testValueObjectsValidInQuery(): void
	{
		$request = $this->createRequest(query: ['stringObject' => 'foo', 'intObject' => '42']);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$result = $this->mapper->mapToObject(ValueMapperInput::class, $context);

		self::assertSame('foo', $result->stringObject->value);
		self::assertSame(42, $result->intObject->value);
	}

	public function testValueObjectsInvalidQueryType(): void
	{
		$request = $this->createRequest(query: ['stringObject' => 42, 'intObject' => '12']);
		$context = SymfonyRequestContextFactory::createFrom($request);

		// Query should be always only strings
		$this->expectInvalidRequest([
			'stringObject' => 'This value is not valid.',
		]);

		$this->mapper->mapToObject(ValueMapperInput::class, $context);
	}

	public function testValueObjectsInvalidTypesInQuery(): void
	{
		$request = $this->createRequest(query: ['stringObject' => '42', 'intObject' => '12.4']);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$this->expectInvalidRequest([
			'intObject' => 'This value is not valid.',
		]);

		$this->mapper->mapToObject(ValueMapperInput::class, $context);
	}

	public function testValueObjectInParamConfigInQuery(): void
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

	public function testInvalidEnumType(): void
	{
		$request = $this->createRequest(query: ['status' => 'drafts']);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$this->expectInvalidRequest([
			'status' => 'The value you selected is not a valid choice.',
		]);
		$this->mapper->mapToObject(EnumInput::class, $context);
	}

	public function testMapFromBodyParameters(): void
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

	public function testMapWithCustomParameterNames(): void
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

	public function testExceptionExtraOneKey(): void
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

	public function testExceptionExtraTwoKeys(): void
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

	public function testExceptionExtraThreeKeys(): void
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

	public function testExceptionMissingRequiredKeyWithCustomKey(): void
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

	public function testMapWithoutLocationAttributesForGet(): void
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

	public function testMapWithoutLocationAttributesForPost(): void
	{
		$request = $this->createRequest('POST', body: ['name' => 'John', 'age' => 30]);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$result = $this->mapper->mapToObject(SimpleInput::class, $context);

		self::assertInstanceOf(SimpleInput::class, $result);
		self::assertSame('John', $result->name);
		self::assertSame(30, $result->age);
	}

	public function testMapWithMissingRequiredParameter(): void
	{
		$request = $this->createRequest('POST', body: []);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$this->expectInvalidRequest([
			'id' => 'This field is missing.',
		]);
		$this->mapper->mapToObject(ArticleInput::class, $context);
	}

	public function testSinglePresetValue(): void
	{
		$request = $this->createRequest('POST', body: [
			'id' => 1,
			'title' => 'Test Article',
		]);
		$context = SymfonyRequestContextFactory::createFrom($request, new RequestMapperConfiguration(presetValues: ['content' => 'text']));

		$input = $this->mapper->mapToObject(ArticleInput::class, $context);

		self::assertSame('text', $input->content);
	}

	public function testOverrideValue(): void
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

	public function testMultiplePresetValues(): void
	{
		$request = $this->createRequest('POST', body: [
			'id' => 1,
		]);
		$context = SymfonyRequestContextFactory::createFrom($request, new RequestMapperConfiguration(presetValues: ['title' => 'Title text', 'content' => 'text']));

		$input = $this->mapper->mapToObject(ArticleInput::class, $context);

		self::assertSame('text', $input->content);
		self::assertSame('Title text', $input->title);
	}

	public function testWithInvalidPresetValue(): void
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

	public function testWithMultipleInvalidPresetValues(): void
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

}

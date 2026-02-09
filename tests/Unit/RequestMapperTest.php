<?php declare(strict_types = 1);

namespace Tests\Unit;

use Shredio\RequestMapper\Attribute\RequestParam;
use Shredio\RequestMapper\Request\Exception\InvalidRequestException;
use Shredio\RequestMapper\Request\RequestContext;
use Shredio\RequestMapper\Request\RequestLocation;
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

final class RequestMapperTest extends MapperTestCase
{

	public function testMapFromPathParameters(): void
	{
		$request = $this->createRequest(path: ['id' => '123']);
		$context = $this->createContextForSimpleArticleInput($request);

		$result = $this->mapper->map(SimpleArticleInput::class, $context);

		self::assertInstanceOf(SimpleArticleInput::class, $result);
		self::assertSame(123, $result->id);
		self::assertSame('general', $result->category);
		self::assertTrue($result->published);
	}

	public function testMapFromQueryParameters(): void
	{
		$request = $this->createRequest(query: ['category' => 'tech', 'published' => '0'], path: ['id' => '456']);
		$context = $this->createContextForSimpleArticleInput($request);

		$result = $this->mapper->map(SimpleArticleInput::class, $context);

		self::assertInstanceOf(SimpleArticleInput::class, $result);
		self::assertSame(456, $result->id);
		self::assertSame('tech', $result->category);
		self::assertFalse($result->published);
	}

	public function testEnumType(): void
	{
		$request = $this->createRequest(query: ['status' => 'draft']);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$result = $this->mapper->map(EnumInput::class, $context);

		self::assertInstanceOf(EnumInput::class, $result);
		self::assertSame('draft', $result->status->value);
	}

	public function testValueObjectsValidInBody(): void
	{
		$request = $this->createRequest('POST', body: ['stringObject' => 'foo', 'intObject' => 42]);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$result = $this->mapper->map(ValueMapperInput::class, $context);

		self::assertSame('foo', $result->stringObject->value);
		self::assertSame(42, $result->intObject->value);
	}

	public function testValueObjectsInvalidTypesInBody(): void
	{
		$request = $this->createRequest('POST', body: ['stringObject' => 42, 'intObject' => 'foo']);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$this->expectInvalidRequest([
			'stringObject' => 'This value should be of type string.',
		]);

		$this->mapper->map(ValueMapperInput::class, $context);
	}

	public function testValueObjectsValidInQuery(): void
	{
		$request = $this->createRequest(query: ['stringObject' => 'foo', 'intObject' => '42']);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$result = $this->mapper->map(ValueMapperInput::class, $context);

		self::assertSame('foo', $result->stringObject->value);
		self::assertSame(42, $result->intObject->value);
	}

	public function testValueObjectsInvalidQueryType(): void
	{
		$request = $this->createRequest(query: ['stringObject' => 42, 'intObject' => '12']);
		$context = SymfonyRequestContextFactory::createFrom($request);

		// Query should be always only strings
		$this->expectInvalidRequest([
			'stringObject' => 'This value should be of type string.',
		]);

		$this->mapper->map(ValueMapperInput::class, $context);
	}

	public function testValueObjectsInvalidTypesInQuery(): void
	{
		$request = $this->createRequest(query: ['stringObject' => '42', 'intObject' => '12.4']);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$this->expectInvalidRequest([
			'intObject' => 'This value should be of type int.',
		]);

		$this->mapper->map(ValueMapperInput::class, $context);
	}

	public function testValueObjectInParamConfigInQuery(): void
	{
		$request = $this->createRequest(query: ['stringObject' => '42', 'intObject' => '12']);
		$context = SymfonyRequestContextFactory::createFrom($request, [
			'stringObject' => new RequestParam(location: RequestLocation::Query),
			'intObject' => new RequestParam(location: RequestLocation::Query),
		]);

		$result = $this->mapper->map(ValueMapperInput::class, $context);

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
		$this->mapper->map(EnumInput::class, $context);
	}

	public function testMapFromBodyParameters(): void
	{
		$request = $this->createRequest('POST', body: [
			'title' => 'Test Article',
			'content' => 'This is test content',
		]);
		$context = SymfonyRequestContextFactory::createFrom($request, location: RequestLocation::Body);

		$result = $this->mapper->map(SimpleBodyInput::class, $context);

		self::assertInstanceOf(SimpleBodyInput::class, $result);
		self::assertSame('Test Article', $result->title);
		self::assertSame('This is test content', $result->content);
	}

	public function testMapWithCustomParameterNames(): void
	{
		$request = $this->createRequest(query: ['filter' => 'active'], path: ['userId' => '100'], headers: ['X-API-Key' => 'secret123']);
		$context = SymfonyRequestContextFactory::createFrom($request, [
			'id' => new RequestParam(sourceKey: 'userId', location: RequestLocation::Route),
			'apiKey' => new RequestParam(sourceKey: 'X-API-Key', location: RequestLocation::Header),
		]);

		$result = $this->mapper->map(UserInput::class, $context);

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
		$this->mapper->map(UserInput::class, $context);
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
		$this->mapper->map(UserInput::class, $context);
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
		$this->mapper->map(UserInput::class, $context);
	}

	public function testExceptionMissingRequiredKeyWithCustomKey(): void
	{
		$request = $this->createRequest();
		$context = SymfonyRequestContextFactory::createFrom($request, [
			'id' => new RequestParam(sourceKey: 'userId', location: RequestLocation::Route),
		]);

		$this->expectInvalidRequest([
			'userId' => 'This field is missing.',
		]);
		$this->mapper->map(UserInput::class, $context);
	}

	public function testMapWithoutLocationAttributesForGet(): void
	{
		$request = $this->createRequest(query: ['name' => 'John', 'age' => '30']);
		$context = SymfonyRequestContextFactory::createFrom($request, [
			'age' => new RequestParam(),
		]);

		$result = $this->mapper->map(SimpleInput::class, $context);

		self::assertInstanceOf(SimpleInput::class, $result);
		self::assertSame('John', $result->name);
		self::assertSame(30, $result->age);
	}

	public function testMapWithoutLocationAttributesForPost(): void
	{
		$request = $this->createRequest('POST', body: ['name' => 'John', 'age' => 30]);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$result = $this->mapper->map(SimpleInput::class, $context);

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
		$this->mapper->map(ArticleInput::class, $context);
	}

	/**
	 * @param array<string, string> $messages field => message
	 */
	private function expectInvalidRequest(array $messages): void
	{
		self::expectException(InvalidRequestException::class);

		$expression = '/.*?';
		foreach ($messages as $field => $message) {
			$expression .= sprintf("Field \"%s\": %s\n", preg_quote($field, '/'), preg_quote($message, '/'));
		}
		$expression = trim($expression) . '/';

		self::expectExceptionMessageMatches($expression);
	}

	private function createRequest(
		string $method = 'GET',
		array $query = [],
		array $path = [],
		?array $body = null,
		array $headers = [],
	): Request
	{
		if ($body !== null) {
			$headers = array_merge($headers, ['Content-Type' => 'application/json']);
		}

		$request = new Request($query, attributes: $path, content: $body !== null ? json_encode($body) : null);
		$request->setMethod($method);
		foreach ($headers as $headerName => $headerValue) {
			$request->headers->set($headerName, $headerValue);
		}

		return $request;
	}

	private function createContextForSimpleArticleInput(Request $request): RequestContext
	{
		return SymfonyRequestContextFactory::createFrom($request, [
			'id' => new RequestParam(location: RequestLocation::Route),
			'published' => new RequestParam(location: RequestLocation::Query),
		]);
	}

}

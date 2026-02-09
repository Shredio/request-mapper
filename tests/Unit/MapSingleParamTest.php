<?php declare(strict_types = 1);

namespace Tests\Unit;

use Shredio\RequestMapper\Attribute\RequestParam;
use Shredio\RequestMapper\Request\Exception\InvalidRequestException;
use Shredio\RequestMapper\Request\RequestLocation;
use Shredio\RequestMapper\Request\SingleRequestParameter;
use Shredio\RequestMapper\Symfony\SymfonyRequestContextFactory;
use Shredio\TypeSchema\Context\TypeContext;
use Shredio\TypeSchema\Error\ErrorElement;
use Shredio\TypeSchema\Mapper\ClassMapper;
use Shredio\TypeSchema\TypeSchema;
use Symfony\Component\HttpFoundation\Request;
use Tests\Fixtures\IntValueObject;
use Tests\Fixtures\Status;
use Tests\Fixtures\StringValueObject;
use Tests\MapperTestCase;

final class MapSingleParamTest extends MapperTestCase
{

	protected function getCustomClassMappers(): array
	{
		return [
			new readonly class extends ClassMapper {

				public function isSupported(string $className): bool
				{
					return in_array($className, [IntValueObject::class, StringValueObject::class], true);
				}

				public function create(string $className, mixed $valueToParse, TypeContext $context): object
				{
					if ($className === IntValueObject::class) {
						$value = TypeSchema::get()->int()->parse($valueToParse, $context);
						if ($value instanceof ErrorElement) {
							return $value;
						}

						return new IntValueObject($value);
					}

					if ($className === StringValueObject::class) {
						$value = TypeSchema::get()->string()->parse($valueToParse, $context);
						if ($value instanceof ErrorElement) {
							return $value;
						}

						return new StringValueObject($value);
					}

					throw new \LogicException('Should not happen.');
				}

			}
		];
	}

	public function testMapSingleParamIntegerFromQuery(): void
	{
		$request = $this->createRequest(query: ['userId' => '42']);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$param = new SingleRequestParameter(
			name: 'userId',
			typeSchema: TypeSchema::get()->int(),
			isNullable: false,
			isOptional: false,
		);

		$result = $this->mapper->mapSingleParam($param, 'TestController', new RequestParam(), $context);

		self::assertSame(42, $result);
	}

	public function testMapSingleParamStringFromQuery(): void
	{
		$request = $this->createRequest(query: ['name' => 'John Doe']);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$param = new SingleRequestParameter(
			name: 'name',
			typeSchema: TypeSchema::get()->string(),
			isNullable: false,
			isOptional: false,
		);

		$result = $this->mapper->mapSingleParam($param, 'TestController', new RequestParam(), $context);

		self::assertSame('John Doe', $result);
	}

	public function testMapSingleParamBooleanTrueFromQuery(): void
	{
		$request = $this->createRequest(query: ['active' => '1']);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$param = new SingleRequestParameter(
			name: 'active',
			typeSchema: TypeSchema::get()->bool(),
			isNullable: false,
			isOptional: false,
		);

		$result = $this->mapper->mapSingleParam($param, 'TestController', new RequestParam(), $context);

		self::assertTrue($result);
	}

	public function testMapSingleParamBooleanFalseFromQuery(): void
	{
		$request = $this->createRequest(query: ['active' => '0']);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$param = new SingleRequestParameter(
			name: 'active',
			typeSchema: TypeSchema::get()->bool(),
			isNullable: false,
			isOptional: false,
		);

		$result = $this->mapper->mapSingleParam($param, 'TestController', new RequestParam(), $context);

		self::assertFalse($result);
	}

	public function testMapSingleParamIntegerFromRoute(): void
	{
		$request = $this->createRequest(path: ['id' => '123']);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$param = new SingleRequestParameter(
			name: 'id',
			typeSchema: TypeSchema::get()->int(),
			isNullable: false,
			isOptional: false,
		);

		$result = $this->mapper->mapSingleParam($param, 'TestController', new RequestParam(location: RequestLocation::Route), $context);

		self::assertSame(123, $result);
	}

	public function testMapSingleParamFromBodyStrict(): void
	{
		$request = $this->createRequest('POST', body: ['age' => 25]);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$param = new SingleRequestParameter(
			name: 'age',
			typeSchema: TypeSchema::get()->int(),
			isNullable: false,
			isOptional: false,
		);

		$result = $this->mapper->mapSingleParam($param, 'TestController', new RequestParam(location: RequestLocation::Body), $context);

		self::assertSame(25, $result);
	}

	public function testMapSingleParamFromBodyStrictFailsWithString(): void
	{
		$request = $this->createRequest('POST', body: ['age' => '25']);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$param = new SingleRequestParameter(
			name: 'age',
			typeSchema: TypeSchema::get()->int(),
			isNullable: false,
			isOptional: false,
		);

		$this->expectInvalidRequest(['age' => 'This value is not valid.']);

		$this->mapper->mapSingleParam($param, 'TestController', new RequestParam(location: RequestLocation::Body), $context);
	}

	public function testMapSingleParamFromHeader(): void
	{
		$request = $this->createRequest(headers: ['X-API-Key' => 'secret123']);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$param = new SingleRequestParameter(
			name: 'apiKey',
			typeSchema: TypeSchema::get()->string(),
			isNullable: false,
			isOptional: false,
		);

		$result = $this->mapper->mapSingleParam($param, 'TestController', new RequestParam(sourceKey: 'X-API-Key', location: RequestLocation::Header), $context);

		self::assertSame('secret123', $result);
	}

	public function testMapSingleParamFromHeaderCaseInsensitive(): void
	{
		$request = $this->createRequest(headers: ['X-Custom-Header' => 'value']);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$param = new SingleRequestParameter(
			name: 'customHeader',
			typeSchema: TypeSchema::get()->string(),
			isNullable: false,
			isOptional: false,
		);

		$result = $this->mapper->mapSingleParam($param, 'TestController', new RequestParam(sourceKey: 'x-custom-header', location: RequestLocation::Header), $context);

		self::assertSame('value', $result);
	}

	public function testMapSingleParamOptionalWithDefaultValue(): void
	{
		$request = $this->createRequest(query: []);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$param = new SingleRequestParameter(
			name: 'limit',
			typeSchema: TypeSchema::get()->int(),
			isNullable: false,
			isOptional: true,
			defaultValue: 10,
		);

		$result = $this->mapper->mapSingleParam($param, 'TestController', new RequestParam(), $context);

		self::assertSame(10, $result);
	}

	public function testMapSingleParamOptionalWithNullDefault(): void
	{
		$request = $this->createRequest(query: []);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$param = new SingleRequestParameter(
			name: 'filter',
			typeSchema: TypeSchema::get()->string(),
			isNullable: true,
			isOptional: true,
			defaultValue: null,
		);

		$result = $this->mapper->mapSingleParam($param, 'TestController', new RequestParam(), $context);

		self::assertNull($result);
	}

	public function testMapSingleParamOptionalProvidedOverridesDefault(): void
	{
		$request = $this->createRequest(query: ['limit' => '50']);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$param = new SingleRequestParameter(
			name: 'limit',
			typeSchema: TypeSchema::get()->int(),
			isNullable: false,
			isOptional: true,
			defaultValue: 10,
		);

		$result = $this->mapper->mapSingleParam($param, 'TestController', new RequestParam(), $context);

		self::assertSame(50, $result);
	}

	public function testMapSingleParamWithCustomSourceKey(): void
	{
		$request = $this->createRequest(query: ['user_id' => '999']);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$param = new SingleRequestParameter(
			name: 'userId',
			typeSchema: TypeSchema::get()->int(),
			isNullable: false,
			isOptional: false,
		);

		$result = $this->mapper->mapSingleParam($param, 'TestController', new RequestParam(sourceKey: 'user_id'), $context);

		self::assertSame(999, $result);
	}

	public function testMapSingleParamMissingRequiredParameter(): void
	{
		$request = $this->createRequest(query: []);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$param = new SingleRequestParameter(
			name: 'id',
			typeSchema: TypeSchema::get()->int(),
			isNullable: false,
			isOptional: false,
		);

		$this->expectInvalidRequest(['id' => 'This field is missing.']);

		$this->mapper->mapSingleParam($param, 'TestController', new RequestParam(), $context);
	}

	public function testMapSingleParamInvalidTypeInQuery(): void
	{
		$request = $this->createRequest(query: ['age' => 'not-a-number']);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$param = new SingleRequestParameter(
			name: 'age',
			typeSchema: TypeSchema::get()->int(),
			isNullable: false,
			isOptional: false,
		);

		$this->expectInvalidRequest(['age' => 'This value is not valid.']);

		$this->mapper->mapSingleParam($param, 'TestController', new RequestParam(), $context);
	}

	public function testMapSingleParamEnumFromQuery(): void
	{
		$request = $this->createRequest(query: ['status' => 'draft']);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$param = new SingleRequestParameter(
			name: 'status',
			typeSchema: TypeSchema::get()->mapper(Status::class),
			isNullable: false,
			isOptional: false,
		);

		$result = $this->mapper->mapSingleParam($param, 'TestController', new RequestParam(), $context);

		self::assertInstanceOf(Status::class, $result);
		self::assertSame('draft', $result->value);
	}

	public function testMapSingleParamInvalidEnumValue(): void
	{
		$request = $this->createRequest(query: ['status' => 'invalid']);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$param = new SingleRequestParameter(
			name: 'status',
			typeSchema: TypeSchema::get()->mapper(Status::class),
			isNullable: false,
			isOptional: false,
		);

		$this->expectInvalidRequest(['status' => 'The value you selected is not a valid choice.']);

		$this->mapper->mapSingleParam($param, 'TestController', new RequestParam(), $context);
	}

	public function testMapSingleParamLenientConversionFromQuery(): void
	{
		$request = $this->createRequest(query: ['price' => '19.99']);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$param = new SingleRequestParameter(
			name: 'price',
			typeSchema: TypeSchema::get()->float(),
			isNullable: false,
			isOptional: false,
		);

		$result = $this->mapper->mapSingleParam($param, 'TestController', new RequestParam(), $context);

		self::assertSame(19.99, $result);
	}

	public function testMapSingleParamArrayFromBody(): void
	{
		$request = $this->createRequest('POST', body: ['tags' => ['php', 'testing', 'mapper']]);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$param = new SingleRequestParameter(
			name: 'tags',
			typeSchema: TypeSchema::get()->list(TypeSchema::get()->string()),
			isNullable: false,
			isOptional: false,
		);

		$result = $this->mapper->mapSingleParam($param, 'TestController', new RequestParam(location: RequestLocation::Body), $context);

		self::assertSame(['php', 'testing', 'mapper'], $result);
	}

	public function testMapSingleParamNullableWithNullValue(): void
	{
		$request = $this->createRequest('POST', body: ['description' => null]);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$param = new SingleRequestParameter(
			name: 'description',
			typeSchema: TypeSchema::get()->nullable(TypeSchema::get()->string()),
			isNullable: true,
			isOptional: false,
		);

		$result = $this->mapper->mapSingleParam($param, 'TestController', new RequestParam(location: RequestLocation::Body), $context);

		self::assertNull($result);
	}

	public function testMapSingleParamNullableWithValue(): void
	{
		$request = $this->createRequest('POST', body: ['description' => 'Some text']);
		$context = SymfonyRequestContextFactory::createFrom($request);

		$param = new SingleRequestParameter(
			name: 'description',
			typeSchema: TypeSchema::get()->nullable(TypeSchema::get()->string()),
			isNullable: true,
			isOptional: false,
		);

		$result = $this->mapper->mapSingleParam($param, 'TestController', new RequestParam(location: RequestLocation::Body), $context);

		self::assertSame('Some text', $result);
	}

	public function testMapSingleParamFromAttribute(): void
	{
		$request = $this->createRequest();
		$request->attributes->set('userId', '777');
		$context = SymfonyRequestContextFactory::createFrom($request);

		$param = new SingleRequestParameter(
			name: 'userId',
			typeSchema: TypeSchema::get()->int(),
			isNullable: false,
			isOptional: false,
		);

		$result = $this->mapper->mapSingleParam($param, 'TestController', new RequestParam(location: RequestLocation::Attribute), $context);

		self::assertSame(777, $result);
	}

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

}

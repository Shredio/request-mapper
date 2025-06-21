<?php declare(strict_types = 1);

namespace Tests\Unit;

use Shredio\RequestMapper\Request\Exception\InvalidRequestException;
use Shredio\RequestMapper\Request\RequestLocation;
use Shredio\RequestMapper\Request\SymfonyRequestContext;
use Shredio\RequestMapper\RequestMapper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Tests\Fixtures\ArticleInput;
use Tests\Fixtures\SimpleArticleInput;
use Tests\Fixtures\SimpleBodyInput;
use Tests\Fixtures\SimpleInput;
use Tests\Fixtures\UserInput;
use Tests\TestCase;

final class RequestMapperTest extends TestCase
{

	private RequestMapper $mapper;

	protected function setUp(): void
	{
		$classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
		$metadataAwareNameConverter = new MetadataAwareNameConverter($classMetadataFactory);
		$extractor = new PropertyInfoExtractor(
			[new ReflectionExtractor()],
			[new ReflectionExtractor()],
			[],
			[new ReflectionExtractor()],
			[new ReflectionExtractor()],
		);

		$normalizers = [
			new ArrayDenormalizer(),
			new DateTimeNormalizer(),
			new BackedEnumNormalizer(),
			new ObjectNormalizer(
				$classMetadataFactory,
				$metadataAwareNameConverter,
				PropertyAccess::createPropertyAccessor(),
				$extractor
			),
		];

		$serializer = new Serializer($normalizers);
		$this->mapper = new RequestMapper($serializer);
	}

	public function testMapFromPathParameters(): void
	{
		$request = new Request();
		$request->attributes->set('id', '123');

		$context = new SymfonyRequestContext($request);

		$result = $this->mapper->map(SimpleArticleInput::class, $context);

		self::assertInstanceOf(SimpleArticleInput::class, $result);
		self::assertSame(123, $result->id);
		self::assertSame('general', $result->category);
		self::assertTrue($result->published);
	}

	public function testMapFromQueryParameters(): void
	{
		$request = new Request(['category' => 'tech', 'published' => '0']);
		$request->attributes->set('id', '456');

		$context = new SymfonyRequestContext($request);

		$result = $this->mapper->map(SimpleArticleInput::class, $context);

		self::assertInstanceOf(SimpleArticleInput::class, $result);
		self::assertSame(456, $result->id);
		self::assertSame('tech', $result->category);
		self::assertFalse($result->published);
	}

	public function testMapFromBodyParameters(): void
	{
		$request = Request::create('/test', 'POST', [], [], [], [], json_encode([
			'title' => 'Test Article',
			'content' => 'This is test content'
		]));
		$request->headers->set('Content-Type', 'application/json');

		$context = new SymfonyRequestContext($request, RequestLocation::Body);

		$result = $this->mapper->map(SimpleBodyInput::class, $context);

		self::assertInstanceOf(SimpleBodyInput::class, $result);
		self::assertSame('Test Article', $result->title);
		self::assertSame('This is test content', $result->content);
	}

	public function testMapWithCustomParameterNames(): void
	{
		$request = new Request(['filter' => 'active']);
		$request->attributes->set('userId', '100');
		$request->headers->set('X-API-Key', 'secret123');

		$context = new SymfonyRequestContext($request);

		$result = $this->mapper->map(UserInput::class, $context);

		self::assertInstanceOf(UserInput::class, $result);
		self::assertSame(100, $result->id);
		self::assertSame('active', $result->filter);
		self::assertSame('secret123', $result->apiKey);
	}

	public function testMapWithoutLocationAttributes(): void
	{
		$request = new Request(['name' => 'John', 'age' => '30']);

		$context = new SymfonyRequestContext($request);

		$result = $this->mapper->map(SimpleInput::class, $context);

		self::assertInstanceOf(SimpleInput::class, $result);
		self::assertSame('John', $result->name);
		self::assertSame(30, $result->age);
	}

	public function testMapWithMissingRequiredParameter(): void
	{
		$request = Request::create('/test', 'POST', [], [], [], [], '{}');
		$request->headers->set('Content-Type', 'application/json');
		$context = new SymfonyRequestContext($request, RequestLocation::Body);

		$this->expectException(InvalidRequestException::class);
		$this->mapper->map(ArticleInput::class, $context);
	}

	public function testMapSimpleType(): void
	{
		$request = new Request(['value' => 42]);
		$context = new SymfonyRequestContext($request);

		$result = $this->mapper->mapSimpleType('int', false, 'value', $context);

		self::assertSame(42, $result);
	}

	public function testMapSimpleTypeNullable(): void
	{
		$request = new Request();
		$context = new SymfonyRequestContext($request);

		$result = $this->mapper->mapSimpleType('string', true, 'missing', $context);

		self::assertNull($result);
	}

}

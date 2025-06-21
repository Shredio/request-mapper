<?php declare(strict_types = 1);

namespace Tests\Unit;

use Shredio\RequestMapper\Request\RequestLocation;
use Shredio\RequestMapper\Request\SymfonyRequestContext;
use Shredio\RequestMapper\RequestMapper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Tests\Fixtures\ComplexInput;
use Tests\TestCase;

final class ComplexMappingTest extends TestCase
{

	private RequestMapper $mapper;

	protected function setUp(): void
	{
		$normalizers = [
			new ArrayDenormalizer(),
			new DateTimeNormalizer(),
			new BackedEnumNormalizer(),
			new ObjectNormalizer(new ClassMetadataFactory(new AttributeLoader())),
		];

		$serializer = new Serializer($normalizers);
		$this->mapper = new RequestMapper($serializer);
	}

	public function testMapFromAllLocations(): void
	{
		$request = Request::create(
			'/test?queryParam=query_value&customQueryName=42',
			'POST',
			server: ['HTTP_HOST' => 'example.com'],
			content: json_encode(['bodyContent' => 'body_value'])
		);
		$request->headers->set('Content-Type', 'application/json');
		$request->headers->set('headerValue', 'header_value');
		$request->attributes->set('attributeValue', 'attr_value');
		$request->attributes->set('pathId', '123');
		$request->attributes->set('customPathName', 'custom_path');

		$context = new SymfonyRequestContext($request, RequestLocation::Body);

		$result = $this->mapper->map(ComplexInput::class, $context);

		self::assertInstanceOf(ComplexInput::class, $result);
		self::assertSame(123, $result->pathId);
		self::assertSame('query_value', $result->queryParam);
		self::assertSame('body_value', $result->bodyContent);
		self::assertSame('header_value', $result->headerValue);
		self::assertSame('attr_value', $result->attributeValue);
		self::assertSame('example.com', $result->serverHost);
		self::assertSame('custom_path', $result->customPath);
		self::assertSame(42, $result->customQuery);
	}

}

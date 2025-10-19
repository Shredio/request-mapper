<?php declare(strict_types = 1);

namespace Tests\Unit;

use Shredio\RequestMapper\Attribute\RequestParam;
use Shredio\RequestMapper\Request\RequestLocation;
use Shredio\RequestMapper\Request\SymfonyRequestContext;
use Symfony\Component\HttpFoundation\Request;
use Tests\Fixtures\ComplexInput;
use Tests\MapperTestCase;

final class ComplexMappingTest extends MapperTestCase
{

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

		$context = new SymfonyRequestContext($request, [
			'pathId' => new RequestParam(location: RequestLocation::Route),
			'queryParam' => new RequestParam(location: RequestLocation::Query),
			'headerValue' => new RequestParam(location: RequestLocation::Header),
			'attributeValue' => new RequestParam(location: RequestLocation::Attribute),
			'serverHost' => new RequestParam('HTTP_HOST', RequestLocation::Server),
			'customPath' => new RequestParam('customPathName', RequestLocation::Route),
			'customQuery' => new RequestParam('customQueryName', RequestLocation::Query),
		]);

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

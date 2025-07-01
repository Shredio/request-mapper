<?php declare(strict_types = 1);

namespace Tests\Unit\Symfony;

use Shredio\RequestMapper\Attribute\StringBodyFromRequest;
use Shredio\RequestMapper\Request\Exception\MissingHttpBodyException;
use Shredio\RequestMapper\Symfony\StringBodyFromRequestControllerArgumentResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Tests\TestCase;

final class StringBodyFromRequestControllerArgumentResolverTest extends TestCase
{

	private StringBodyFromRequestControllerArgumentResolver $resolver;

	protected function setUp(): void
	{
		$this->resolver = new StringBodyFromRequestControllerArgumentResolver();
	}

	public function testResolveWithStringBodyFromRequestAttribute(): void
	{
		$request = Request::create('/test', 'POST', [], [], [], [], 'raw body content');
		
		$argument = new ArgumentMetadata(
			'body',
			'string',
			false,
			false,
			null,
			false,
			[new StringBodyFromRequest()]
		);

		$result = iterator_to_array($this->resolver->resolve($request, $argument));

		self::assertCount(1, $result);
		self::assertSame('raw body content', $result[0]);
	}

	public function testResolveWithEmptyBodyAndNullableParameter(): void
	{
		$request = Request::create('/test', 'POST');
		
		$argument = new ArgumentMetadata(
			'body',
			'string',
			false,
			false,
			null,
			true, // nullable
			[new StringBodyFromRequest()]
		);

		$result = iterator_to_array($this->resolver->resolve($request, $argument));

		self::assertCount(1, $result);
		self::assertNull($result[0]);
	}

	public function testResolveWithEmptyBodyAndNonNullableParameterThrowsException(): void
	{
		$request = Request::create('/test', 'POST');
		
		$argument = new ArgumentMetadata(
			'body',
			'string',
			false,
			false,
			null,
			false, // not nullable
			[new StringBodyFromRequest()]
		);

		$this->expectException(MissingHttpBodyException::class);
		$this->expectExceptionMessage('Missing HTTP body for parameter "body".');

		iterator_to_array($this->resolver->resolve($request, $argument));
	}

	public function testResolveWithoutStringBodyFromRequestAttribute(): void
	{
		$request = Request::create('/test', 'POST', [], [], [], [], 'raw body content');
		
		$argument = new ArgumentMetadata(
			'body',
			'string',
			false,
			false,
			null,
			false,
			[] // no attributes
		);

		$result = iterator_to_array($this->resolver->resolve($request, $argument));

		self::assertCount(0, $result);
	}

	public function testResolveWithJsonBody(): void
	{
		$jsonContent = '{"message": "hello world"}';
		$request = Request::create('/test', 'POST', [], [], [], [], $jsonContent);
		$request->headers->set('Content-Type', 'application/json');
		
		$argument = new ArgumentMetadata(
			'body',
			'string',
			false,
			false,
			null,
			false,
			[new StringBodyFromRequest()]
		);

		$result = iterator_to_array($this->resolver->resolve($request, $argument));

		self::assertCount(1, $result);
		self::assertSame($jsonContent, $result[0]);
	}

	public function testResolveWithTrimEnabled(): void
	{
		$request = Request::create('/test', 'POST', [], [], [], [], '  trimmed content  ');
		
		$argument = new ArgumentMetadata(
			'body',
			'string',
			false,
			false,
			null,
			false,
			[new StringBodyFromRequest(trim: true)]
		);

		$result = iterator_to_array($this->resolver->resolve($request, $argument));

		self::assertCount(1, $result);
		self::assertSame('trimmed content', $result[0]);
	}

	public function testResolveWithTrimDisabled(): void
	{
		$request = Request::create('/test', 'POST', [], [], [], [], '  untrimmed content  ');
		
		$argument = new ArgumentMetadata(
			'body',
			'string',
			false,
			false,
			null,
			false,
			[new StringBodyFromRequest(trim: false)]
		);

		$result = iterator_to_array($this->resolver->resolve($request, $argument));

		self::assertCount(1, $result);
		self::assertSame('  untrimmed content  ', $result[0]);
	}

}

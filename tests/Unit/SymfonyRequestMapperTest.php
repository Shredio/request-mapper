<?php declare(strict_types = 1);

namespace Tests\Unit;

use AutoMapper\AutoMapper;
use PHPUnit\Framework\Attributes\TestWith;
use Shredio\RequestMapper\DefaultRequestInputMapper;
use Shredio\RequestMapper\Enum\RequestLocation;
use Shredio\RequestMapper\Exception\InvalidRequestException;
use Shredio\RequestMapper\Mapper\JoliCodeObjectMapper;
use Shredio\RequestMapper\Parameter\RequestParameter;
use Shredio\RequestMapper\Symfony\SymfonyRequestMappingContext;
use Symfony\Component\HttpFoundation\Request;
use Tests\Common\ArticleInput;
use Tests\Common\ArticleRequest;
use Tests\Common\SingleInput;
use Tests\TestCase;

final class SymfonyRequestMapperTest extends TestCase
{

	#[TestWith(['GET', true])]
	#[TestWith(['POST', false])]
	#[TestWith(['PATCH', false])]
	#[TestWith(['PUT', false])]
	public function testSimpleRequests(string $method, bool $valid): void
	{
		$request = new Request([
			'subject' => 'Test subject',
			'content' => 'Test content',
			'publishedAt' => '2023-10-01T00:00:00Z',
			'authorId' => 1,
		]);
		$request->setMethod($method);
		$context = new SymfonyRequestMappingContext($request);

		$requestMapper = $this->createObjectMapper();

		if ($valid) {
			$input = $requestMapper->map(ArticleInput::class, $context);

			$this->assertSame('Test subject', $input->subject);
		} else {
			$this->expectException(InvalidRequestException::class);

			$requestMapper->map(ArticleInput::class, $context);
		}
	}

	public function testParameterLocation(): void
	{
		$request = new Request([
			'subject' => 'Test subject',
			'content' => 'Test content',
			'publishedAt' => '2023-10-01T00:00:00Z',
			'authorId' => 1,
		], attributes: [
			'authorId' => 2,
		]);
		$request->setMethod('GET');
		$context = new SymfonyRequestMappingContext($request, [
			new RequestParameter('authorId', location: RequestLocation::Attribute),
		]);

		$requestMapper = $this->createObjectMapper();

		$input = $requestMapper->map(ArticleInput::class, $context);

		$this->assertSame(2, $input->authorId);
	}

	public function testInvalidParameterLocation(): void
	{
		$request = new Request([
			'subject' => 'Test subject',
			'content' => 'Test content',
			'publishedAt' => '2023-10-01T00:00:00Z',
			'authorId' => 1,
		]);
		$request->setMethod('GET');
		$context = new SymfonyRequestMappingContext($request, [
			new RequestParameter('authorId', location: RequestLocation::Attribute),
		]);

		$requestMapper = $this->createObjectMapper();

		$this->expectException(InvalidRequestException::class);

		$requestMapper->map(ArticleInput::class, $context);
	}

	#[TestWith([RequestLocation::Attribute, 'attribute'])]
	#[TestWith([RequestLocation::Query, 'query'])]
	#[TestWith([RequestLocation::Body, 'body'])]
	#[TestWith([RequestLocation::Header, 'header'])]
	#[TestWith([RequestLocation::Server, 'server'])]
	public function testLocations(RequestLocation $location, string $expect): void
	{
		$request = new Request(['name' => 'query'], ['name' => 'body'], ['name' => 'attribute'], server: ['name' => 'server', 'HTTP_name' => 'header']);

		$context = new SymfonyRequestMappingContext($request, [
			new RequestParameter('name', location: $location),
		]);

		$requestMapper = $this->createObjectMapper();

		$input = $requestMapper->map(SingleInput::class, $context);

		$this->assertSame($expect, $input->name);
	}

	public function testMediator(): void
	{
		$request = new Request([
			'subject' => 'Test subject',
			'content' => 'Test content',
			'publishedAt' => '2023-10-01T00:00:00Z',
			'authorId' => 1,
		]);
		$context = new SymfonyRequestMappingContext($request, mediatorClass: ArticleRequest::class);

		$requestMapper = $this->createObjectMapper();

		$article = $requestMapper->map(ArticleInput::class, $context);

		var_dump($article);
	}

	private function createObjectMapper(): DefaultRequestInputMapper
	{
		return new DefaultRequestInputMapper(new JoliCodeObjectMapper(AutoMapper::create()), self::TempDir, true);
	}

}

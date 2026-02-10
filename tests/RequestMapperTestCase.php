<?php declare(strict_types = 1);

namespace Tests;

use Shredio\RequestMapper\Attribute\RequestParam;
use Shredio\RequestMapper\Exception\LogicException;
use Shredio\RequestMapper\Request\Exception\InvalidRequestException;
use Shredio\RequestMapper\Request\RequestContext;
use Shredio\RequestMapper\Request\RequestLocation;
use Shredio\RequestMapper\RequestMapperConfiguration;
use Shredio\RequestMapper\Symfony\SymfonyRequestContextFactory;
use Symfony\Component\HttpFoundation\Request;

abstract class RequestMapperTestCase extends MapperTestCase
{

	/**
	 * @param array<string, string> $messages field => message
	 */
	protected function expectInvalidPresetValues(array $messages): void
	{
		self::expectException(LogicException::class);

		$expression = '/.*?';
		foreach ($messages as $field => $message) {
			$expression .= sprintf("Field \"%s\": %s\n", preg_quote($field, '/'), preg_quote($message, '/'));
		}
		$expression = trim($expression) . '/';

		self::expectExceptionMessageMatches($expression);
	}

	/**
	 * @param array<string, string> $messages field => message
	 */
	protected function expectInvalidRequest(array $messages): void
	{
		self::expectException(InvalidRequestException::class);

		$expression = '/.*?';
		foreach ($messages as $field => $message) {
			$expression .= sprintf("Field \"%s\": %s\n", preg_quote($field, '/'), preg_quote($message, '/'));
		}
		$expression = trim($expression) . '/';

		self::expectExceptionMessageMatches($expression);
	}

	protected function createRequest(
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

	protected function createContextForSimpleArticleInput(Request $request): RequestContext
	{
		return SymfonyRequestContextFactory::createFrom($request, new RequestMapperConfiguration([
			'id' => new RequestParam(location: RequestLocation::Route),
			'published' => new RequestParam(location: RequestLocation::Query),
		]));
	}

}

<?php declare(strict_types = 1);

namespace Tests\Unit;

use Shredio\RequestMapper\Conversion\Discriminator;
use Shredio\RequestMapper\Request\Exception\InvalidRequestException;
use Shredio\RequestMapper\Request\Exception\StructuralRequestException;
use Shredio\RequestMapper\Request\Exception\ValidationRequestException;
use Shredio\RequestMapper\RequestMapperConfiguration;
use Shredio\RequestMapper\Symfony\SymfonyRequestContextFactory;
use Shredio\TypeSchema\Enum\ExtraKeysBehavior;
use Shredio\TypeSchema\TypeSchema;
use Tests\Fixtures\CatInput;
use Tests\Fixtures\DogInput;
use Tests\RequestMapperTestCase;

/**
 * Verifies that the mapper distinguishes structural failures (HTTP 400) from
 * validation failures (HTTP 422), and that a single structural failure in a
 * payload makes the whole request structural.
 */
final class RequestExceptionCategoryTest extends RequestMapperTestCase
{

	public function testWrongFieldTypeIsStructural(): void
	{
		$request = $this->createRequest(query: ['id' => ['not', 'an', 'int']]);
		$context = SymfonyRequestContextFactory::createFrom($request, new RequestMapperConfiguration());

		$this->expectException(StructuralRequestException::class);
		$this->mapper->mapToArray(
			TypeSchema::get()->arrayShape(['id' => TypeSchema::get()->int()]),
			$context,
		);
	}

	public function testMissingRequiredFieldIsStructural(): void
	{
		$request = $this->createRequest();
		$context = SymfonyRequestContextFactory::createFrom($request, new RequestMapperConfiguration());

		$this->expectException(StructuralRequestException::class);
		$this->mapper->mapToArray(
			TypeSchema::get()->arrayShape(['name' => TypeSchema::get()->string()]),
			$context,
		);
	}

	public function testExtraFieldIsStructural(): void
	{
		$request = $this->createRequest(query: ['name' => 'Alice', 'unexpected' => 'value']);
		$context = SymfonyRequestContextFactory::createFrom($request, new RequestMapperConfiguration());

		$this->expectException(StructuralRequestException::class);
		$this->mapper->mapToArray(
			TypeSchema::get()->arrayShape(
				['name' => TypeSchema::get()->string()],
				extraItems: ExtraKeysBehavior::Reject,
			),
			$context,
		);
	}

	public function testRangeConstraintIsValidation(): void
	{
		$request = $this->createRequest(query: ['age' => '5']);
		$context = SymfonyRequestContextFactory::createFrom($request, new RequestMapperConfiguration());

		$this->expectException(ValidationRequestException::class);
		$this->mapper->mapToArray(
			TypeSchema::get()->arrayShape(['age' => TypeSchema::get()->intRange(min: 18, max: 120)]),
			$context,
		);
	}

	public function testMixedStructuralAndValidationIsStructural(): void
	{
		$request = $this->createRequest(query: ['id' => ['not', 'an', 'int'], 'age' => '5']);
		$context = SymfonyRequestContextFactory::createFrom($request, new RequestMapperConfiguration());

		$this->expectException(StructuralRequestException::class);
		$this->mapper->mapToArray(
			TypeSchema::get()->arrayShape([
				'id' => TypeSchema::get()->int(),
				'age' => TypeSchema::get()->intRange(min: 18, max: 120),
			]),
			$context,
		);
	}

	public function testValidationRequestExceptionIsAlsoInvalidRequestException(): void
	{
		$request = $this->createRequest(query: ['age' => '5']);
		$context = SymfonyRequestContextFactory::createFrom($request, new RequestMapperConfiguration());

		try {
			$this->mapper->mapToArray(
				TypeSchema::get()->arrayShape(['age' => TypeSchema::get()->intRange(min: 18, max: 120)]),
				$context,
			);
			self::fail('Expected ValidationRequestException was not thrown.');
		} catch (ValidationRequestException $exception) {
			self::assertInstanceOf(InvalidRequestException::class, $exception);
		}
	}

	public function testDiscriminatorMissingKeyIsStructural(): void
	{
		$request = $this->createRequest('POST', body: ['name' => 'Whiskers']);
		$context = SymfonyRequestContextFactory::createFrom($request, new RequestMapperConfiguration(
			discriminator: new Discriminator('type', [
				'cat' => CatInput::class,
				'dog' => DogInput::class,
			]),
		));

		$this->expectException(StructuralRequestException::class);
		$this->mapper->mapToObject(CatInput::class, $context);
	}

	public function testDiscriminatorUnknownValueIsValidation(): void
	{
		$request = $this->createRequest('POST', body: ['type' => 'bird', 'name' => 'Tweety']);
		$context = SymfonyRequestContextFactory::createFrom($request, new RequestMapperConfiguration(
			discriminator: new Discriminator('type', [
				'cat' => CatInput::class,
				'dog' => DogInput::class,
			]),
		));

		$this->expectException(ValidationRequestException::class);
		$this->mapper->mapToObject(CatInput::class, $context);
	}

	public function testMergeIsStructuralWhenAnyExceptionIsStructural(): void
	{
		$merged = InvalidRequestException::merge([
			new ValidationRequestException('a', []),
			new StructuralRequestException('b', []),
		]);

		self::assertInstanceOf(StructuralRequestException::class, $merged);
	}

	public function testMergeIsValidationWhenAllExceptionsAreValidation(): void
	{
		$merged = InvalidRequestException::merge([
			new ValidationRequestException('a', []),
			new ValidationRequestException('b', []),
		]);

		self::assertInstanceOf(ValidationRequestException::class, $merged);
	}

}

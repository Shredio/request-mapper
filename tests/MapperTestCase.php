<?php declare(strict_types = 1);

namespace Tests;

use Nette\Utils\FileSystem;
use Nette\Utils\Finder;
use Shredio\RequestMapper\RequestMapper;
use Shredio\TypeSchema\Context\TypeContext;
use Shredio\TypeSchema\Error\ErrorElement;
use Shredio\TypeSchema\Mapper\ClassMapper;
use Shredio\TypeSchema\Mapper\Jit\ClassMapperCompileHashedTargetProvider;
use Shredio\TypeSchema\Mapper\Jit\JustInTimeClassMapperProvider;
use Shredio\TypeSchema\Mapper\RegistryClassMapperProvider;
use Shredio\TypeSchema\TypeSchema;
use Shredio\TypeSchema\TypeSchemaProcessor;
use Shredio\TypeSchemaCompiler\MapperCompiler;
use Tests\Fixtures\IntValueObject;
use Tests\Fixtures\StringValueObject;

abstract class MapperTestCase extends TestCase
{

	protected const string GeneratedDir = __DIR__ . '/Generated';

	protected RequestMapper $mapper;

	protected function setUp(): void
	{
		parent::setUp();

		$compiler = MapperCompiler::create(true, false);
		$classMapperProvider = new JustInTimeClassMapperProvider(
			new ClassMapperCompileHashedTargetProvider(self::GeneratedDir, 'Tests\Generated\%sMapper'),
			$compiler,
			new RegistryClassMapperProvider([...RegistryClassMapperProvider::createDefaultClassMappers(), ...$this->getCustomClassMappers()]),
		);
		$schemaProcessor = TypeSchemaProcessor::createDefault(
			classMapperProvider: $classMapperProvider,
		);
		$this->mapper = new RequestMapper($schemaProcessor);
	}

	public static function tearDownAfterClass(): void
	{
		parent::tearDownAfterClass();

		foreach (Finder::findFiles('*.php')->in(self::GeneratedDir) as $file) {
			FileSystem::delete($file->getPathname());
		}
	}

	/**
	 * @return list<ClassMapper<object>>
	 */
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

}

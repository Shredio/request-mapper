<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Symfony\Bundle;

use Shredio\RequestMapper\DefaultRequestInputMapper;
use Shredio\RequestMapper\Mapper\JoliCodeObjectMapper;
use Shredio\RequestMapper\Mapper\ObjectMapper;
use Shredio\RequestMapper\RequestInputMapper;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class RequestMapperBundle extends AbstractBundle
{

	/**
	 * @param mixed[] $config
	 */
	public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
	{
		$services = $container->services();

		$cacheDir = $builder->getParameter('kernel.cache_dir');

		assert(is_string($cacheDir));

		$services->set($this->prefix('request_input_mapper'), DefaultRequestInputMapper::class)
			->autowire()
			->arg('$tempDir', sprintf('%s/RequestMapper', $cacheDir))
			->alias(RequestInputMapper::class, $this->prefix('request_input_mapper'));

		$services->set($this->prefix('object_mapper'), JoliCodeObjectMapper::class)
			->autowire()
			->alias(ObjectMapper::class, $this->prefix('object_mapper'));
	}

	private function prefix(string $name): string
	{
		return 'request_mapper.' . $name;
	}

}

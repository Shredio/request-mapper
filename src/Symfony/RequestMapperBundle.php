<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Symfony;

use Shredio\RequestMapper\Mapper\RequestValueMapper;
use Shredio\RequestMapper\Mapper\Shipmonk\ShipmonkRequestObjectMapper;
use Shredio\RequestMapper\RequestMapper;
use Shredio\RequestMapper\RequestObjectMapper;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

final class RequestMapperBundle extends AbstractBundle
{

	/**
	 * @param mixed[] $config
	 */
	public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
	{
		$services = $container->services();
		$services->set(ShipmonkRequestObjectMapper::class)
			->autowire()
			->alias(RequestObjectMapper::class, ShipmonkRequestObjectMapper::class);
		$services->set(RequestMapper::class)
			->autowire();
		$services->set('shredio.controller_argument_resolver', RequestMapperArgumentResolver::class)
			->args([service(RequestMapper::class)])
			->tag('kernel.event_subscriber')
			->tag('controller.argument_value_resolver', ['priority' => 110, 'name' => RequestMapperArgumentResolver::class]);
		$builder->registerForAutoconfiguration(RequestValueMapper::class)
			->addTag('request_value_mapper');
	}

}

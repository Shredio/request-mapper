<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Symfony;

use Shredio\RequestMapper\Request\RequestContextFactory;
use Shredio\RequestMapper\RequestParameterMapper;
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

		$services->set(SymfonyRequestContextFactory::class)
			->autowire();
		$services->alias(RequestContextFactory::class, SymfonyRequestContextFactory::class);

		$services->set(RequestParameterMapper::class)
			->autowire();

		$services->set('shredio.controller_argument_resolver', RequestMapperArgumentResolver::class)
			->args([service(RequestParameterMapper::class)])
			->tag('kernel.event_subscriber')
			->tag('controller.argument_value_resolver', ['priority' => 110, 'name' => RequestMapperArgumentResolver::class]);
	}

}

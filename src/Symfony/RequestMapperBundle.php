<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Symfony;

use Shredio\RequestMapper\RequestMapper;
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
		$services->set(RequestMapper::class)
			->autowire();
		$services->set('shredio.controller_argument_resolver', MapFromRequestControllerArgumentResolver::class)
			->autowire()
			->tag('kernel.event_subscriber')
			->tag('controller.argument_value_resolver');
		$services->set('shredio.controller_argument_resolver', StringBodyFromRequestControllerArgumentResolver::class)
			->autowire()
			->tag('controller.argument_value_resolver');
	}

}

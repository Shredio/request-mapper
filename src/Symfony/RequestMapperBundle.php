<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Symfony;

use Shredio\RequestMapper\DefaultRequestMapper;
use Shredio\RequestMapper\Request\RequestContextFactory;
use Shredio\RequestMapper\RequestMapper;
use Shredio\RequestMapper\RequestParameterMapper;
use Shredio\TypeSchema\TypeSchemaProcessor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\RequestStack;
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
			->args([service(RequestStack::class)]);
		$services->alias(RequestContextFactory::class, SymfonyRequestContextFactory::class);

		$services->set('shredio.request_mapper', RequestParameterMapper::class)
			->args([service(TypeSchemaProcessor::class)]);

		$services->set(DefaultRequestMapper::class)
			->args([
				service('shredio.request_mapper'),
				service(RequestContextFactory::class),
			]);
		$services->alias(RequestMapper::class, DefaultRequestMapper::class);

		$services->set('shredio.controller_argument_resolver', RequestMapperArgumentResolver::class)
			->args([service('shredio.request_mapper')])
			->tag('kernel.event_subscriber')
			->tag('controller.argument_value_resolver', ['priority' => 110, 'name' => RequestMapperArgumentResolver::class]);
	}

}

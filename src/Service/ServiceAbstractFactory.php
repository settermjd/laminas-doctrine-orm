<?php

declare(strict_types=1);

namespace Skar\LaminasDoctrineORM\Service;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\AbstractFactoryInterface;
use Psr\Container\ContainerInterface;

use function array_key_exists;
use function explode;
use function is_callable;
use function is_subclass_of;
use function sprintf;
use function str_starts_with;
use function vsprintf;

class ServiceAbstractFactory implements AbstractFactoryInterface
{
    /**
     * @inheritDoc
     */
    public function canCreate(ContainerInterface $container, string $requestedName): bool
    {
        return str_starts_with($requestedName, 'doctrine.');
    }

    /**
     * @inheritDoc
     */
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): mixed
    {
        $config      = $container->get('config');
        $factories   = $config['doctrine_factories'];
        $servicePath = explode('.', $requestedName);

        if (empty($servicePath[1]) || ! array_key_exists($servicePath[1], $factories)) {
            throw new ServiceNotFoundException(
                sprintf('Factory for service "%s" could not be found', $servicePath[1])
            );
        }
        $serviceName = $servicePath[1];
        $serviceKey  = 'orm_default';

        if (! empty($servicePath[2])) {
            $serviceKey = $servicePath[2];
        }

        $serviceConfig = [];
        if (array_key_exists($serviceName, $config['doctrine'])) {
            $serviceConfig = $config['doctrine'][$serviceName];
        }
        if (array_key_exists($serviceKey, $serviceConfig)) {
            $serviceConfig = $serviceConfig[$serviceKey];
        }

        if (! is_subclass_of($factories[$serviceName], AbstractFactory::class)) {
            throw new ServiceNotCreatedException(vsprintf('"%s" must be inherit from "%s"', [
                $factories[$serviceName],
                AbstractFactory::class,
            ]));
        }

        $factory = new $factories[$serviceName]($serviceConfig);

        if (! is_callable($factory)) {
            throw new ServiceNotCreatedException(vsprintf('"%s" must be callable', [
                $factories[$serviceName],
            ]));
        }

        return $factory($container, $requestedName, $options);
    }
}

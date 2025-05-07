<?php

declare(strict_types=1);

namespace Skar\LaminasDoctrineORM\Service;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\IndexedReader;
use Doctrine\Common\Annotations\PsrCachedReader;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\Driver\AnnotationDriver;
use Doctrine\Persistence\Mapping\Driver\DefaultFileLocator;
use Doctrine\Persistence\Mapping\Driver\FileDriver;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use InvalidArgumentException;
use Laminas\Stdlib\ArrayUtils;
use Psr\Container\ContainerInterface;

use function class_exists;
use function is_subclass_of;
use function sprintf;

class DriverFactory extends AbstractFactory
{
    /**
     * @inheritDoc
     */
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null
    ): MappingDriver {
        return $this->createDriver($this->config, $container);
    }

    protected function createDriver(
        array $config,
        ContainerInterface $container
    ): MappingDriverChain|FileDriver|AttributeDriver|MappingDriver {
        if (! $class = $config['class']) {
            throw new InvalidArgumentException('Drivers must specify a class');
        }

        if (! class_exists($class)) {
            throw new InvalidArgumentException(sprintf('Driver with type "%s" could not be found', $class));
        }

        if ($class === AttributeDriver::class || is_subclass_of($class, AttributeDriver::class)) {
            // AttributeDriver extends AnnotationDriver in violation of the Liskov substitution principle
            $driver = new $class($config['paths']);
        } elseif ($class === AnnotationDriver::class || is_subclass_of($class, AnnotationDriver::class)) {
            // Special options for AnnotationDrivers.
            $reader = new PsrCachedReader(
                new IndexedReader(new AnnotationReader()),
                $container->get($this->getServiceName('cache'))
            );
            $driver = new $class($reader, $config['paths']);
        } else {
            $driver = new $class($config['paths']);
        }

        if ($config['extension'] && $driver instanceof FileDriver) {
            $locator = $driver->getLocator();

            if ($locator::class !== DefaultFileLocator::class) {
                throw new InvalidArgumentException(sprintf(
                    'Discovered file locator for driver of type "%s" is an instance of "%s". This factory '
                    . 'supports only the DefaultFileLocator when an extension is set for the file locator',
                    $driver::class,
                    $locator::class
                ));
            }

            $driver->setLocator(new DefaultFileLocator($locator->getPaths(), $config['extension']));
        }

        // Extra post-create options for DriverChain.
        if ($driver instanceof MappingDriverChain && $config['drivers']) {
            foreach ($config['drivers'] as $namespace => $driverConfig) {
                $driverConfig = ArrayUtils::merge($this->getDefaultConfig(), $driverConfig);
                $driver->addDriver($this->createDriver($driverConfig, $container), $namespace);
            }
        }

        return $driver;
    }

    /**
     * @inheritDoc
     */
    protected function getDefaultConfig(): array
    {
        return [
            'class'     => MappingDriverChain::class,
            'paths'     => [],
            'cache'     => 'array',
            'extension' => null,
            'drivers'   => [],
        ];
    }
}

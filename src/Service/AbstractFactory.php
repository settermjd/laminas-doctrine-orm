<?php

declare(strict_types=1);

namespace Skar\LaminasDoctrineORM\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\Stdlib\ArrayUtils;

use function sprintf;

abstract class AbstractFactory implements FactoryInterface
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = ArrayUtils::merge($this->getDefaultConfig(), $config);
    }

    /**
     * Returns service full name
     */
    protected function getServiceName(string $type, ?string $key = null): string
    {
        return sprintf(
            'doctrine.%s.%s',
            $type,
            $key ?? $this->config[$type]
        );
    }

    /**
     * Returns default config
     */
    abstract protected function getDefaultConfig(): array;
}

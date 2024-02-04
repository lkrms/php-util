<?php declare(strict_types=1);

namespace Lkrms\Container\Contract;

/**
 * Implemented by classes that need to know when they are used to resolve a
 * service from a container
 */
interface ServiceAwareInterface
{
    /**
     * Called when the instance is used to resolve a service from a container
     *
     * If the instance also implements {@see ContainerAwareInterface},
     * {@see ContainerAwareInterface::setContainer()} is called first.
     *
     * @param class-string $service
     */
    public function setService(string $service): void;
}
<?php declare(strict_types=1);

namespace Salient\Contract\Container;

/**
 * @template T of ContainerInterface
 */
interface HasContainer
{
    /**
     * Get the object's service container
     *
     * @return T
     */
    public function app(): ContainerInterface;

    /**
     * Get the object's service container
     *
     * @return T
     */
    public function container(): ContainerInterface;
}

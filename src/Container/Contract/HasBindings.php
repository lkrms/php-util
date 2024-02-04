<?php declare(strict_types=1);

namespace Lkrms\Container\Contract;

/**
 * Implemented by service providers with container bindings
 */
interface HasBindings
{
    /**
     * Get bindings to register with a container
     *
     * @return array<class-string,class-string>
     */
    public static function getBindings(): array;
}
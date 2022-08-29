<?php

declare(strict_types=1);

namespace Lkrms\Contract;

use Lkrms\Container\Container;

/**
 * Provides services that can be bound to a container
 *
 * In this context, a service is an `interface` implemented by the class,
 * allowing it to be registered with (or "bound to") a {@see Container} as the
 * concrete class to instantiate when the abstract service is requested.
 *
 * Containers resolve service/`interface` names to instances:
 * - when they are requested explicitly via {@see Container::get()}, and
 * - when they are used as type hints in the constructors of dependencies
 *   encountered while resolving a call to {@see Container::get()}.
 *
 * If the class should be instantiated as a singleton (or "shared instance"),
 * implement {@see IBindableSingleton}, otherwise implement {@see IBindable}.
 */
interface IBindable extends IHasContainer
{
    /**
     * Get a list of services provided by the class
     *
     * @return string[]
     */
    public static function getBindable(): array;

    /**
     * Get an array that maps concrete classes to more specific subclasses
     *
     * When a container receives a request from an {@see IBindable} for a class
     * bound to a more specific subclass by {@see IBindable::getBindings()}, it
     * returns an instance of the subclass.
     *
     * These bindings only apply:
     * - when the class's dependencies are being resolved, and
     * - when using a {@see \Lkrms\Container\ContextContainer} to work with a
     *   container in the context of the class (see
     *   {@see Container::context()}).
     *
     * @return array<string,string>
     */
    public static function getBindings(): array;

}

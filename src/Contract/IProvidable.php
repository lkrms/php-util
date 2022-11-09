<?php

declare(strict_types=1);

namespace Lkrms\Contract;

use Lkrms\Support\ArrayKeyConformity;

/**
 * Can be created by an IProvider to represent an external entity
 *
 */
interface IProvidable extends ReceivesService, ReturnsService
{
    /**
     * Get the provider servicing the entity
     *
     */
    public function provider(): ?IProvider;

    /**
     * Get the context in which the entity is being serviced
     *
     */
    public function context(): ?IProvidableContext;

    /**
     * Get the entity the instance was resolved from
     *
     * Consider the following scenario:
     *
     * - `Faculty` is a `SyncEntity` subclass
     * - `CustomFaculty` is a subclass of `Faculty`
     * - `CustomFaculty` is bound to the service container as `Faculty`:
     *   ```php
     *   $this->app()->bind(Faculty::class, CustomFaculty::class);
     *   ```
     * - `$provider` implements `FacultyProvider`
     * - A `Faculty` object is requested from `$provider` for faculty #1:
     *   ```php
     *   $faculty = $provider->with(Faculty::class)->get(1);
     *   ```
     *
     * `$faculty` is now a `Faculty` service and an instance of `CustomFaculty`,
     * so this code:
     *
     * ```php
     * print_r([
     *     'class'   => get_class($faculty),
     *     'service' => $faculty->service(),
     * ]);
     * ```
     *
     * will produce the following output:
     *
     * ```
     * Array
     * (
     *     [class] => CustomFaculty
     *     [service] => Faculty
     * )
     * ```
     */
    public function service(): string;

    /**
     * Called immediately after instantiation by a provider's service container
     *
     * @return $this
     * @throws \RuntimeException if the instance already has a provider.
     */
    public function setProvider(IProvider $provider);

    /**
     * Called immediately after instantiation, then as needed by the provider
     *
     * @return $this
     */
    public function setContext(?IProvidableContext $ctx);

    /**
     * @return static
     */
    public static function provide(array $data, IProvider $provider, ?IProvidableContext $context = null);

    /**
     * @param iterable<array> $dataList
     * @return iterable<static>
     */
    public static function provideList(iterable $dataList, IProvider $provider, int $conformity = ArrayKeyConformity::NONE, ?IProvidableContext $context = null): iterable;

    #### Deprecated ####

    /**
     * @deprecated Use {@see IProvidable::provide()} instead
     * @param array<int|string,int|string|array<int,int|string>>|null $keyMap
     * @return static
     */
    public static function fromProvider(IProvider $provider, array $data, callable $callback = null, array $keyMap = null);

    /**
     * @deprecated Use {@see IProvidable::provideList()} instead
     * @param iterable<array> $list
     * @param array<int|string,int|string|array<int,int|string>>|null $keyMap
     * @return iterable<static>
     */
    public static function listFromProvider(IProvider $provider, iterable $list, callable $callback = null, array $keyMap = null): iterable;

}

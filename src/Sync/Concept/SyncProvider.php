<?php

declare(strict_types=1);

namespace Lkrms\Sync\Concept;

use Closure;
use Lkrms\Concern\HasContainer;
use Lkrms\Contract\IBindableSingleton;
use Lkrms\Facade\Compute;
use Lkrms\Facade\Convert;
use Lkrms\Support\DateFormatter;
use Lkrms\Sync\Contract\ISyncDefinition;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Sync\Support\SyncClosureBuilder;
use Lkrms\Sync\Support\SyncContext;
use Lkrms\Sync\Support\SyncEntityProvider;
use Lkrms\Sync\Support\SyncOperation;
use ReflectionClass;
use RuntimeException;
use UnexpectedValueException;

/**
 * Base class for providers that sync entities to and from third-party backends
 * via their APIs
 *
 */
abstract class SyncProvider implements ISyncProvider, IBindableSingleton
{
    use HasContainer;

    /**
     * Surface the provider's implementation of sync operations for an entity
     * via an ISyncDefinition object
     *
     */
    abstract protected function getDefinition(string $entity): ISyncDefinition;

    /**
     * Return a stable identifier that, together with the name of the class,
     * uniquely identifies the connected backend instance
     *
     * This method must be idempotent for each backend instance the provider
     * connects to. The return value should correspond to the smallest possible
     * set of stable metadata that uniquely identifies the specific data source
     * backing the connected instance.
     *
     * This could include:
     * - an endpoint URI (if backend instances are URI-specific or can be
     *   expressed as an immutable URI)
     * - a tenant ID
     * - an installation GUID
     *
     * It should not include:
     * - usernames, API keys, tokens, or other identifiers with a shorter
     *   lifespan than the data source itself
     * - values that aren't unique to the connected data source
     * - case-insensitive values (unless normalised first)
     *
     * @return string[]
     */
    abstract protected function _getBackendIdentifier(): array;

    /**
     * Specify how to encode dates for the backend and/or the timezone to apply
     *
     * The {@see DateFormatter} returned will be cached for the lifetime of the
     * {@see SyncProvider} instance.
     *
     */
    abstract protected function _getDateFormatter(): DateFormatter;

    /**
     * Get an array that maps concrete classes to more specific subclasses
     *
     * {@inheritdoc}
     *
     * Bind any {@see SyncEntity} classes customised for this provider to their
     * generic parent classes by overriding this method, e.g.:
     *
     * ```php
     * public static function getBindings(): array
     * {
     *     return [
     *         Post::class => CustomPost::class,
     *         User::class => CustomUser::class,
     *     ];
     * }
     * ```
     *
     */
    public static function getBindings(): array
    {
        return [];
    }

    /**
     * @var string|null
     */
    private $BackendHash;

    /**
     * @var DateFormatter|null
     */
    private $DateFormatter;

    /**
     * @var array<string,string[]>
     */
    private static $SyncProviderInterfaces = [];

    /**
     * @var array<string,Closure>
     */
    private $MagicMethodClosures = [];

    /**
     * @see SyncProvider::_getBackendIdentifier()
     */
    final public function getBackendHash(): string
    {
        return $this->BackendHash
            ?: ($this->BackendHash = Compute::hash(...$this->_getBackendIdentifier()));
    }

    final public function getDateFormatter(): DateFormatter
    {
        return $this->DateFormatter
            ?: ($this->DateFormatter = $this->_getDateFormatter());
    }

    final public static function getBindable(): array
    {
        if (!is_null($interfaces = self::$SyncProviderInterfaces[static::class] ?? null))
        {
            return $interfaces;
        }
        $class      = new ReflectionClass(static::class);
        $interfaces = [];
        foreach ($class->getInterfaces() as $name => $interface)
        {
            if ($interface->isSubclassOf(ISyncProvider::class))
            {
                $interfaces[] = $name;
            }
        }
        return self::$SyncProviderInterfaces[static::class] = $interfaces;
    }

    final public function with(string $syncEntity, $context = null): SyncEntityProvider
    {
        $container = ($context instanceof SyncContext
            ? $context->container()
            : ($context ?: $this->container()))->inContextOf(static::class);
        $context = ($context instanceof SyncContext
            ? $context->withContainer($container)
            : new SyncContext($container));

        return $container->get(
            SyncEntityProvider::class,
            $syncEntity,
            $this,
            $this->getDefinition($syncEntity),
            $context
        );
    }

    final public function __call(string $name, array $arguments)
    {
        if (($closure = $this->MagicMethodClosures[$name = strtolower($name)] ?? false) === false)
        {
            $closure = SyncClosureBuilder::get(static::class)->getSyncOperationFromMethodClosure($name, $this);
            $this->MagicMethodClosures[$name] = $closure;
        }
        if ($closure)
        {
            return $closure(...$arguments);
        }

        throw new RuntimeException("Call to undefined method: " . static::class . "::$name()");
    }

}

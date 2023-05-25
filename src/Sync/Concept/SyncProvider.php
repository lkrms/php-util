<?php declare(strict_types=1);

namespace Lkrms\Sync\Concept;

use Closure;
use Lkrms\Container\Container;
use Lkrms\Contract\IPipeline;
use Lkrms\Contract\IService;
use Lkrms\Facade\Convert;
use Lkrms\Support\DateFormatter;
use Lkrms\Support\Pipeline;
use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncDefinition;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Sync\Support\SyncContext;
use Lkrms\Sync\Support\SyncEntityProvider;
use Lkrms\Sync\Support\SyncIntrospector;
use Lkrms\Sync\Support\SyncStore;
use Lkrms\Utility\Environment;
use RuntimeException;

/**
 * Base class for providers that sync entities to and from third-party backends
 * via their APIs
 *
 */
abstract class SyncProvider implements ISyncProvider, IService
{
    /**
     * Surface the provider's implementation of sync operations for an entity
     * via an ISyncDefinition object
     *
     * @template T of ISyncEntity
     * @param class-string<T> $entity
     * @return ISyncDefinition<T,static>
     */
    abstract public function getDefinition(string $entity): ISyncDefinition;

    /**
     * Get a stable list of values that, together with the name of the class,
     * uniquely identifies the backend instance
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
     * @return array<string|\Stringable>
     */
    abstract public function getBackendIdentifier(): array;

    /**
     * Specify how to encode dates for the backend and/or the timezone to apply
     *
     * The {@see DateFormatter} returned will be cached for the lifetime of the
     * {@see SyncProvider} instance.
     *
     */
    abstract protected function getDateFormatter(): DateFormatter;

    /**
     * Get an array that maps concrete classes to more specific subclasses
     *
     * {@inheritdoc}
     *
     * Bind any {@see ISyncEntity} classes customised for this provider to their
     * generic parent classes by overriding this method, e.g.:
     *
     * ```php
     * public static function getContextualBindings(): array
     * {
     *     return [
     *         Post::class => CustomPost::class,
     *         User::class => CustomUser::class,
     *     ];
     * }
     * ```
     *
     */
    public static function getContextualBindings(): array
    {
        return [];
    }

    public function description(): ?string
    {
        return null;
    }

    /**
     * @var Container
     */
    protected $App;

    /**
     * @var Environment
     */
    protected $Env;

    /**
     * @var SyncStore
     */
    protected $Store;

    /**
     * @var int|null
     */
    private $Id;

    /**
     * @var string|null
     */
    private $Hash;

    /**
     * @var DateFormatter|null
     */
    private $DateFormatter;

    /**
     * @var array<string,Closure>
     */
    private $MagicMethodClosures = [];

    public function __construct(Container $app, Environment $environment, SyncStore $store)
    {
        $this->App = $app;
        $this->Env = $environment;
        $this->Store = $store;

        $this->Store->provider($this);
    }

    final public function setProviderId(int $providerId, string $providerHash)
    {
        $this->Id = $providerId;
        $this->Hash = $providerHash;

        return $this;
    }

    final public function app(): Container
    {
        return $this->App;
    }

    final public function container(): Container
    {
        return $this->App;
    }

    final public function env(): Environment
    {
        return $this->Env;
    }

    final public function store(): SyncStore
    {
        return $this->Store;
    }

    /**
     * Get a new pipeline bound to the provider's container
     *
     */
    final protected function pipeline(): IPipeline
    {
        return Pipeline::create($this->App);
    }

    final public function dateFormatter(): DateFormatter
    {
        return $this->DateFormatter
            ?: ($this->DateFormatter = $this->getDateFormatter());
    }

    final public static function getServices(): array
    {
        return SyncIntrospector::get(static::class)->getSyncProviderInterfaces();
    }

    /**
     * @template TEntity of ISyncEntity
     * @param class-string<TEntity> $syncEntity
     * @return SyncEntityProvider<TEntity,static>
     */
    final public function with(string $syncEntity, $context = null): SyncEntityProvider
    {
        $this->Store->entityType($syncEntity);

        $container = ($context instanceof ISyncContext
            ? $context->container()
            : ($context ?: $this->container()))->inContextOf(static::class);
        $context = $context instanceof ISyncContext
            ? $context->withContainer($container)
            : $container->get(SyncContext::class);

        return $container->get(
            SyncEntityProvider::class,
            [$syncEntity, $this, $this->getDefinition($syncEntity), $context]
        );
    }

    final public function __call(string $name, array $arguments)
    {
        if (($closure = $this->MagicMethodClosures[$name = strtolower($name)] ?? false) === false) {
            $closure = SyncIntrospector::get(static::class)->getMagicSyncOperationClosure($name, $this);
            $this->MagicMethodClosures[$name] = $closure;
        }
        if ($closure) {
            return $closure(...$arguments);
        }

        throw new RuntimeException('Call to undefined method: ' . static::class . "::$name()");
    }

    final public function getProviderId(): ?int
    {
        return $this->Id;
    }

    final public function getProviderHash(bool $binary = false): ?string
    {
        if (is_null($this->Hash)) {
            return null;
        }

        return $binary ? $this->Hash : bin2hex($this->Hash);
    }
}

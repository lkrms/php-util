<?php

declare(strict_types=1);

namespace Lkrms\Sync\Provider;

use Lkrms\Console\Console;
use Lkrms\Curler\CachingCurler;
use Lkrms\Curler\Curler;
use Lkrms\Curler\CurlerHeaders;
use Lkrms\Sync\Concept\SyncProvider;
use Lkrms\Sync\Contract\ISyncDefinition;
use Lkrms\Sync\Support\HttpSyncDefinition;
use Lkrms\Sync\Support\HttpSyncDefinitionBuilder;

/**
 * Base class for HTTP-based RESTful API providers
 *
 */
abstract class HttpSyncProvider extends SyncProvider
{
    /**
     * Return the base URL of the upstream API
     *
     * `$path` should be ignored unless the provider uses endpoint-specific base
     * URLs to connect to the API. It should never be added to the return value.
     *
     * Called once per {@see HttpSyncProvider::getCurler()} call.
     *
     * @param string|null $path The endpoint requested via
     * {@see HttpSyncProvider::getCurler()}.
     * @return string
     */
    abstract protected function getBaseUrl(?string $path): string;

    /**
     * Return headers to use when connecting to the upstream API
     *
     * Called once per {@see HttpSyncProvider::getCurler()} call.
     *
     * @param string|null $path The endpoint requested via
     * {@see HttpSyncProvider::getCurler()}.
     * @return CurlerHeaders|null
     */
    abstract protected function getCurlerHeaders(?string $path): ?CurlerHeaders;

    /**
     * The time, in seconds, before upstream responses expire
     *
     * Return `null` to disable response caching (the default) or `0` to cache
     * upstream responses indefinitely.
     *
     * Called whenever {@see HttpSyncProvider::getCurler()} is called without an
     * explicit `$expiry`.
     *
     * @param string|null $path The endpoint requested via
     * {@see HttpSyncProvider::getCurler()}.
     * @see \Lkrms\Store\CacheStore::set() for more information about `$expiry`
     * values
     */
    protected function getCurlerCacheExpiry(?string $path): ?int
    {
        return null;
    }

    /**
     * Prepare a Curler instance for connecting to the upstream API
     *
     * Called once per {@see HttpSyncProvider::getCurler()} call.
     *
     */
    protected function prepareCurler(Curler $curler): Curler
    {
        return $curler;
    }

    /**
     * Used by CachingCurler when adding request headers to cache keys
     *
     * @param CurlerHeaders $headers
     * @return string[]
     * @see CachingCurler::__construct()
     */
    protected function getCurlerCacheKey(CurlerHeaders $headers): array
    {
        return $headers->getPublicHeaders();
    }

    /**
     * Surface the provider's implementation of sync operations for an entity
     * via an HttpSyncDefinition object
     *
     * Return `null` if no sync operations are implemented for the entity.
     *
     * @param HttpSyncDefinitionBuilder $define A definition builder with
     * `entity()` and `provider()` already applied.
     * @return HttpSyncDefinition|HttpSyncDefinitionBuilder|null
     */
    protected function getHttpDefinition(string $entity, HttpSyncDefinitionBuilder $define)
    {
        return null;
    }

    final protected function getDefinition(string $entity): ?ISyncDefinition
    {
        $def = $this->getHttpDefinition($entity, (new HttpSyncDefinitionBuilder())
            ->entity($entity)
            ->provider($this));

        if ($def instanceof HttpSyncDefinitionBuilder)
        {
            return $def->go();
        }

        return $def;
    }

    /**
     * Get the URL of an API endpoint
     *
     * @param string $path
     * @return string
     */
    final public function getEndpointUrl(string $path): string
    {
        return $this->getBaseUrl($path) . $path;
    }

    /**
     * Get a Curler or CachingCurler instance bound to an API endpoint
     *
     * If `$expiry` is an integer less than `0`, the return value of
     * {@see HttpSyncProvider::getCurlerCacheExpiry()} will be used as the
     * response expiry time.
     */
    final public function getCurler(string $path, ?int $expiry = -1): Curler
    {
        if (!is_null($expiry) && $expiry < 0)
        {
            $expiry = $this->getCurlerCacheExpiry($path);
        }

        if (!is_null($expiry))
        {
            $curler = new CachingCurler(
                $this->getEndpointUrl($path),
                $this->getCurlerHeaders($path),
                $expiry,
                fn(CurlerHeaders $headers) => $this->getCurlerCacheKey($headers)
            );
        }
        else
        {
            $curler = new Curler(
                $this->getEndpointUrl($path),
                $this->getCurlerHeaders($path)
            );
        }

        $this->prepareCurler($curler);

        return $curler;
    }

    public function checkHeartbeat(int $ttl = 300): void
    {
        Console::debugOnce("Not implemented:",
            static::class . "::" . __FUNCTION__);
    }

}

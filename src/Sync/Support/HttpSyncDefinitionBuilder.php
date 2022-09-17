<?php

declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Contract\IContainer;
use Lkrms\Contract\IPipeline;
use Lkrms\Sync\Provider\HttpSyncProvider;

/**
 * A fluent interface for creating HttpSyncDefinition objects
 *
 * @method static $this build(?IContainer $container = null) Create a new HttpSyncDefinitionBuilder (syntactic sugar for 'new HttpSyncDefinitionBuilder()')
 * @method $this entity(string $value)
 * @method $this provider(HttpSyncProvider $value)
 * @method $this path(string $value)
 * @method $this operations(int[] $value)
 * @method $this overrides(array $value)
 * @method $this dataToEntityPipeline(?IPipeline $value)
 * @method $this entityToDataPipeline(?IPipeline $value)
 * @method HttpSyncDefinition go() Return a new HttpSyncDefinition object
 *
 * @uses HttpSyncDefinition
 * @lkrms-generate-command lk-util generate builder --class='Lkrms\Sync\Support\HttpSyncDefinition' --static-builder='build' --terminator='go' --extend='Lkrms\Sync\Support\SyncDefinitionBuilder'
 */
final class HttpSyncDefinitionBuilder extends SyncDefinitionBuilder
{
    /**
     * @internal
     */
    protected static function getClassName(): string
    {
        return HttpSyncDefinition::class;
    }
}

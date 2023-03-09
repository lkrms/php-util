<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Concept\Builder;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IPipeline;
use Lkrms\Sync\Concept\DbSyncProvider;
use Lkrms\Sync\Concept\SyncDefinition;

/**
 * A fluent interface for creating DbSyncDefinition objects
 *
 * @method static $this build(?IContainer $container = null) Create a new DbSyncDefinitionBuilder (syntactic sugar for 'new DbSyncDefinitionBuilder()')
 * @method $this entity(string $value) The ISyncEntity being serviced (see {@see SyncDefinition::$Entity})
 * @method $this provider(DbSyncProvider $value) The ISyncProvider servicing the entity
 * @method $this operations(int[] $value) A list of supported sync operations (see {@see SyncDefinition::$Operations})
 * @method $this table(?string $value) Set DbSyncDefinition::$Table
 * @method $this conformity(int $value) The conformity level of data returned by the provider for this entity (see {@see SyncDefinition::$Conformity})
 * @method $this filterPolicy(int $value) The action to take when filters are ignored by the provider (see {@see SyncDefinition::$FilterPolicy})
 * @method $this overrides(array $value) See {@see SyncDefinition::$Overrides}
 * @method $this dataToEntityPipeline(?IPipeline $value) A pipeline that maps data from the provider to entity-compatible associative arrays, or `null` if mapping is not required (see {@see SyncDefinition::$DataToEntityPipeline})
 * @method $this entityToDataPipeline(?IPipeline $value) A pipeline that maps serialized entities to data compatible with the provider, or `null` if mapping is not required (see {@see SyncDefinition::$EntityToDataPipeline})
 * @method bool isset(string $name) True if a value for $name has been applied to the unresolved DbSyncDefinition by calling $name()
 * @method DbSyncDefinition go() Get a new DbSyncDefinition object
 * @method static DbSyncDefinition|null resolve(DbSyncDefinition|DbSyncDefinitionBuilder|null $object) Resolve a DbSyncDefinitionBuilder or DbSyncDefinition object to a DbSyncDefinition object
 *
 * @uses DbSyncDefinition
 * @lkrms-generate-command lk-util generate builder --static-builder=build --value-checker=isset --terminator=go --static-resolver=resolve 'Lkrms\Sync\Support\DbSyncDefinition'
 */
final class DbSyncDefinitionBuilder extends Builder
{
    /**
     * @internal
     */
    protected static function getClassName(): string
    {
        return DbSyncDefinition::class;
    }
}

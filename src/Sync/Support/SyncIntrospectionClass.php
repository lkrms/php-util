<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Facade\Convert;
use Lkrms\Facade\Reflect;
use Lkrms\Support\IntrospectionClass;
use Lkrms\Sync\Concept\SyncProvider;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Contract\ISyncProvider;
use ReflectionClass;

/**
 * Internal use only
 *
 * @template TClass of object
 * @extends IntrospectionClass<TClass>
 */
final class SyncIntrospectionClass extends IntrospectionClass
{
    /**
     * @var bool|null
     */
    public $IsEntity;

    /**
     * @var bool|null
     */
    public $IsProvider;

    // Related to `SyncEntity`:

    /**
     * @var string|null
     */
    public $EntityNoun;

    /**
     * Not set if the plural class name is the same the singular one
     *
     * @var string|null
     */
    public $EntityPlural;

    // Related to `SyncProvider`:

    /**
     * Interfaces that extend ISyncProvider
     *
     * @var string[]
     */
    public $SyncProviderInterfaces = [];

    /**
     * Entities serviced by ISyncProvider interfaces
     *
     * @var string[]
     */
    public $SyncProviderEntities = [];

    /**
     * Unambiguous lowercase entity basename => entity
     *
     * @var array<string,string>
     */
    public $SyncProviderEntityBasenames = [];

    /**
     * Lowercase method name => sync operation method declared by the provider
     *
     * @var array<string,string>
     */
    public $SyncOperationMethods = [];

    /**
     * Lowercase "magic" sync operation method => [ sync operation, entity ]
     *
     * Used only to map "magic" method names to sync operations. Providers
     * aren't required to service any of them.
     *
     * @var array<string,array{0:int,1:string}>
     * @phpstan-var array<string,array{0:SyncOperation::*,1:string}>
     */
    public $SyncOperationMagicMethods = [];

    /**
     * Signature => closure
     *
     * @var array<string,\Closure>
     */
    public $CreateFromSignatureSyncClosures = [];

    /**
     * Signature => (int) $strict => closure
     *
     * @var array<string,array<int,\Closure>>
     */
    public $CreateSyncEntityFromSignatureClosures = [];

    /**
     * (int) $strict => closure
     *
     * @var array<int,\Closure>
     */
    public $CreateSyncEntityFromClosures = [];

    /**
     * Entity => sync operation => closure
     *
     * @var array<string,array<int,\Closure|null>>
     * @phpstan-var array<string,array<SyncOperation::*,\Closure|null>>
     */
    public $DeclaredSyncOperationClosures = [];

    /**
     * Lowercase "magic" sync operation method => closure
     *
     * @var array<string,\Closure|null>
     */
    public $MagicSyncOperationClosures = [];

    public function __construct(string $class)
    {
        parent::__construct($class);
        $class = $this->Reflector;
        $this->IsEntity = $class->implementsInterface(ISyncEntity::class);
        $this->IsProvider = $class->implementsInterface(ISyncProvider::class);

        if ($this->IsEntity) {
            $this->EntityNoun = Convert::classToBasename($this->Class);
            $plural = $class->getMethod('plural')->invoke(null);
            if (strcasecmp($this->EntityNoun, $plural)) {
                $this->EntityPlural = $plural;
            }
        }

        if (!$this->IsProvider) {
            return;
        }

        $namespace = (new ReflectionClass(SyncProvider::class))->getNamespaceName();
        foreach ($class->getInterfaces() as $name => $interface) {
            if (!$interface->isSubclassOf(ISyncProvider::class)) {
                continue;
            }

            // Add ISyncProvider interfaces to SyncProviderInterfaces
            $this->SyncProviderInterfaces[] = $name;

            // Add the entities they service to SyncProviderEntities
            if (!($entity = SyncIntrospector::providerToEntity($name)) ||
                    !is_a($entity, ISyncEntity::class, true)) {
                continue;
            }
            $entity = SyncIntrospector::get($entity);
            $this->SyncProviderEntities[] = $entity->Class;

            // Map unambiguous lowercase entity basenames to qualified names in
            // SyncProviderEntityBasenames
            $basename = strtolower(Convert::classToBasename($entity->Class));
            $this->SyncProviderEntityBasenames[$basename] =
                array_key_exists($basename, $this->SyncProviderEntityBasenames)
                    ? null
                    : $entity->Class;

            $fn = function (int $operation, string $method) use ($entity, $class, $namespace) {
                // If $method has already been processed, the entity it services
                // is ambiguous and it can't be used
                if (array_key_exists($method, $this->SyncOperationMethods) ||
                        array_key_exists($method, $this->SyncOperationMagicMethods)) {
                    $this->SyncOperationMagicMethods[$method] = $this->SyncOperationMethods[$method] = null;

                    return;
                }
                if ($class->hasMethod($method) &&
                        ($_method = $class->getMethod($method))->isPublic()) {
                    if ($_method->isStatic() ||
                            !strcasecmp(Reflect::getMethodPrototypeClass($_method)->getNamespaceName(), $namespace)) {
                        $this->SyncOperationMethods[$method] = null;

                        return;
                    }
                    $this->SyncOperationMethods[$method] = $_method->name;

                    return;
                }

                /**
                 * @phpstan-var SyncOperation::* $operation
                 */
                $this->SyncOperationMagicMethods[$method] = [$operation, $entity->Class];
            };

            [$noun, $plural] = [
                strtolower($entity->EntityNoun),
                strtolower($entity->EntityPlural)
            ];

            if ($plural) {
                $fn(SyncOperation::CREATE_LIST, 'create' . $plural);
                $fn(SyncOperation::READ_LIST, 'get' . $plural);
                $fn(SyncOperation::UPDATE_LIST, 'update' . $plural);
                $fn(SyncOperation::DELETE_LIST, 'delete' . $plural);
            }
            $fn(SyncOperation::CREATE, 'create' . $noun);
            $fn(SyncOperation::CREATE, 'create_' . $noun);
            $fn(SyncOperation::READ, 'get' . $noun);
            $fn(SyncOperation::READ, 'get_' . $noun);
            $fn(SyncOperation::UPDATE, 'update' . $noun);
            $fn(SyncOperation::UPDATE, 'update_' . $noun);
            $fn(SyncOperation::DELETE, 'delete' . $noun);
            $fn(SyncOperation::DELETE, 'delete_' . $noun);
            $fn(SyncOperation::CREATE_LIST, 'createlist_' . $noun);
            $fn(SyncOperation::READ_LIST, 'getlist_' . $noun);
            $fn(SyncOperation::UPDATE_LIST, 'updatelist_' . $noun);
            $fn(SyncOperation::DELETE_LIST, 'deletelist_' . $noun);
        }
        $this->SyncProviderEntityBasenames = array_filter($this->SyncProviderEntityBasenames);
        $this->SyncOperationMethods = array_filter($this->SyncOperationMethods);
        $this->SyncOperationMagicMethods = array_filter($this->SyncOperationMagicMethods);
    }
}

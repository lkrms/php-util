<?php

declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Closure;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IPipelineImmutable;
use Lkrms\Contract\ITreeNode;
use Lkrms\Contract\ReturnsContainer;
use Lkrms\Support\ArrayKeyConformity;
use Lkrms\Support\PipelineImmutable;
use Lkrms\Sync\Contract\ISyncDefinition;
use Lkrms\Sync\Contract\ISyncProvider;

/**
 * Provides access to a SyncProvider's implementation of sync operations for an
 * entity
 *
 */
abstract class SyncDefinition implements ISyncDefinition
{
    abstract public function getSyncOperationClosure(int $operation): ?Closure;

    /**
     * @var string
     */
    protected $Entity;

    /**
     * @var ISyncProvider
     */
    protected $Provider;

    /**
     * @var int
     */
    protected $Conformity;

    /**
     * @var IPipelineImmutable|null
     */
    protected $DataToEntityPipeline;

    /**
     * @var IPipelineImmutable|null
     */
    protected $EntityToDataPipeline;

    /**
     * @var SyncClosureBuilder
     */
    protected $EntityClosureBuilder;

    /**
     * @var SyncClosureBuilder
     */
    protected $ProviderClosureBuilder;

    /**
     * @param IPipelineImmutable|null $dataToEntityPipeline A pipeline that
     * converts data received from the provider to an associative array from
     * which the entity can be instantiated, or `null` if the entity is not
     * supported or conversion is not required.
     * @param IPipelineImmutable|null $entityToDataPipeline A pipeline that
     * converts a serialized instance of the entity to data compatible with the
     * provider, or `null` if the entity is not supported or conversion is not
     * required.
     */
    public function __construct(string $entity, ISyncProvider $provider, int $conformity = ArrayKeyConformity::NONE, ?IPipelineImmutable $dataToEntityPipeline = null, ?IPipelineImmutable $entityToDataPipeline = null)
    {
        $this->Entity     = $entity;
        $this->Provider   = $provider;
        $this->Conformity = $conformity;
        $this->DataToEntityPipeline = $dataToEntityPipeline;
        $this->EntityToDataPipeline = $entityToDataPipeline;

        $this->EntityClosureBuilder   = SyncClosureBuilder::get($entity);
        $this->ProviderClosureBuilder = SyncClosureBuilder::get(get_class($provider));
    }

    protected function getPipelineToBackend(): IPipelineImmutable
    {
        return $this->EntityToDataPipeline ?: PipelineImmutable::create();
    }

    protected function getPipelineToEntity(): IPipelineImmutable
    {
        return ($this->DataToEntityPipeline ?: PipelineImmutable::create())
            ->then(function (array $entity, SyncContext $ctx) use (&$closure)
            {
                if (!$closure)
                {
                    $closure = in_array($this->Conformity, [ArrayKeyConformity::PARTIAL, ArrayKeyConformity::COMPLETE])
                        ? SyncClosureBuilder::getBound($ctx->Container, $this->Entity)->getCreateFromSignatureClosure(array_keys($entity))
                        : SyncClosureBuilder::getBound($ctx->Container, $this->Entity)->getCreateFromClosure();
                }

                return $closure($entity, $ctx->Container, $ctx->Parent);
            });
    }

}

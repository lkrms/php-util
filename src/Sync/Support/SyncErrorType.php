<?php

declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Concept\ConvertibleEnumeration;

/**
 * Sync error types
 *
 */
final class SyncErrorType extends ConvertibleEnumeration
{
    /**
     * No entities matching the criteria were returned by the provider
     */
    public const ENTITY_NOT_FOUND = 0;

    /**
     * The same entity appears multiple times
     */
    public const ENTITY_NOT_UNIQUE = 1;

    /**
     * The entity should not exist, or has a missing counterpart
     */
    public const ENTITY_NOT_EXPECTED = 2;

    /**
     * The provider does not implement sync operations for the entity
     */
    public const ENTITY_NOT_SUPPORTED = 3;

    /**
     * Hierarchical data contains a circular reference
     */
    public const HIERARCHY_IS_CIRCULAR = 4;

}

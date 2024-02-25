<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\Entity;

use Lkrms\Sync\Concept\SyncEntity;
use Lkrms\Sync\Support\DeferredEntity;
use Salient\Core\Catalog\Cardinality;

/**
 * Represents the state of a Photo entity in a backend
 *
 * @generated
 */
class Photo extends SyncEntity
{
    /**
     * @var int|string|null
     */
    public $Id;

    /**
     * @var Album|DeferredEntity<Album>|null
     */
    public $Album;

    /**
     * @var string|null
     */
    public $Title;

    /**
     * @var string|null
     */
    public $Url;

    /**
     * @var string|null
     */
    public $ThumbnailUrl;

    /**
     * @internal
     */
    public static function getRelationships(): array
    {
        return [
            'Album' => [Cardinality::ONE_TO_ONE => Album::class],
        ];
    }
}

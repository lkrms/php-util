<?php declare(strict_types=1);

namespace Salient\Tests\Sync\Entity;

use Salient\Catalog\Core\Cardinality;
use Salient\Sync\Support\DeferredEntity;
use Salient\Sync\AbstractSyncEntity;

/**
 * Represents the state of a Task entity in a backend
 *
 * @generated
 */
class Task extends AbstractSyncEntity
{
    /**
     * @var int|string|null
     */
    public $Id;

    /**
     * @var User|DeferredEntity<User>|null
     */
    public $User;

    /**
     * @var string|null
     */
    public $Title;

    /**
     * @var bool|null
     */
    public $Completed;

    /**
     * @internal
     */
    public static function getRelationships(): array
    {
        return [
            'User' => [Cardinality::ONE_TO_ONE => User::class],
        ];
    }
}

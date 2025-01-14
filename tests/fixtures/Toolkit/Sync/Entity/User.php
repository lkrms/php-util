<?php declare(strict_types=1);

namespace Salient\Tests\Sync\Entity;

use Salient\Sync\Support\DeferredEntity;
use Salient\Sync\Support\DeferredRelationship;
use Salient\Sync\AbstractSyncEntity;

/**
 * Represents the state of a User entity in a backend
 *
 * @generated
 */
class User extends AbstractSyncEntity
{
    /** @var int|string|null */
    public $Id;
    /** @var string|null */
    public $Name;
    /** @var string|null */
    public $Username;
    /** @var string|null */
    public $Email;
    /** @var array<string,mixed>|null */
    public $Address;
    /** @var string|null */
    public $Phone;
    /** @var array<string,mixed>|null */
    public $Company;
    /** @var array<Task|DeferredEntity<Task>>|DeferredRelationship<Task>|null */
    public $Tasks;
    /** @var array<Post|DeferredEntity<Post>>|DeferredRelationship<Post>|null */
    public $Posts;
    /** @var array<Album|DeferredEntity<Album>>|DeferredRelationship<Album>|null */
    public $Albums;

    /**
     * @internal
     */
    public static function getRelationships(): array
    {
        return [
            'Tasks' => [self::ONE_TO_MANY => Task::class],
            'Posts' => [self::ONE_TO_MANY => Post::class],
            'Albums' => [self::ONE_TO_MANY => Album::class],
        ];
    }
}

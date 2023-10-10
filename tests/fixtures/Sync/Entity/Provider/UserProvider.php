<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\Entity\Provider;

use Lkrms\Support\Iterator\Contract\FluentIteratorInterface;
use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Tests\Sync\Entity\User;

/**
 * Syncs User objects with a backend
 *
 * @method User createUser(ISyncContext $ctx, User $user)
 * @method User getUser(ISyncContext $ctx, int|string|null $id)
 * @method User updateUser(ISyncContext $ctx, User $user)
 * @method User deleteUser(ISyncContext $ctx, User $user)
 * @method FluentIteratorInterface<array-key,User> getUsers(ISyncContext $ctx)
 *
 * @generated by lk-util
 * @salient-generate-command generate sync provider --magic --op='create,get,update,delete,get-list' 'Lkrms\Tests\Sync\Entity\User'
 */
interface UserProvider extends ISyncProvider {}

<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\Provider;

use Lkrms\Contract\IServiceSingleton;
use Lkrms\Curler\Contract\ICurlerHeaders;
use Lkrms\Support\Catalog\ArrayKeyConformity;
use Lkrms\Support\DateFormatter;
use Lkrms\Sync\Catalog\SyncFilterPolicy;
use Lkrms\Sync\Catalog\SyncOperation as OP;
use Lkrms\Sync\Concept\HttpSyncProvider;
use Lkrms\Sync\Support\HttpSyncDefinitionBuilder;
use Lkrms\Sync\Support\SyncContext;
use Lkrms\Tests\Sync\Entity\Provider\PostProvider;
use Lkrms\Tests\Sync\Entity\Provider\UserProvider;
use Lkrms\Tests\Sync\Entity\Post;
use Lkrms\Tests\Sync\Entity\User;
use Lkrms\Utility\Env;

/**
 * @method Post createPost(SyncContext $ctx, Post $post)
 * @method Post getPost(SyncContext $ctx, int|string|null $id)
 * @method Post updatePost(SyncContext $ctx, Post $post)
 * @method Post deletePost(SyncContext $ctx, Post $post)
 * @method iterable<Post> getPosts(SyncContext $ctx)
 * @method User createUser(SyncContext $ctx, User $user)
 * @method User getUser(SyncContext $ctx, int|string|null $id)
 * @method User updateUser(SyncContext $ctx, User $user)
 * @method User deleteUser(SyncContext $ctx, User $user)
 * @method iterable<User> getUsers(SyncContext $ctx)
 */
class JsonPlaceholderApi extends HttpSyncProvider implements IServiceSingleton, PostProvider, UserProvider
{
    public function name(): ?string
    {
        return sprintf('JSONPlaceholder { %s }', $this->getBaseUrl());
    }

    public static function getContextualBindings(): array
    {
        return [
            Post::class => \Lkrms\Tests\Sync\CustomEntity\Post::class,
            User::class => \Lkrms\Tests\Sync\CustomEntity\User::class,
        ];
    }

    public function getBackendIdentifier(): array
    {
        return [$this->getBaseUrl()];
    }

    protected function getDateFormatter(): DateFormatter
    {
        return new DateFormatter();
    }

    protected function getBaseUrl(?string $path = null): string
    {
        // Set JSON_PLACEHOLDER_BASE_URL=https://jsonplaceholder.typicode.com to
        // test against the live version if necessary
        return Env::get('JSON_PLACEHOLDER_BASE_URL', 'http://localhost:3001');
    }

    protected function getHeaders(?string $path): ?ICurlerHeaders
    {
        return null;
    }

    protected function getExpiry(?string $path): ?int
    {
        return 24 * 3600;
    }

    protected function buildHttpDefinition(string $entity, HttpSyncDefinitionBuilder $defB): HttpSyncDefinitionBuilder
    {
        switch ($entity) {
            case Post::class:
                return $defB
                    ->operations([OP::READ, OP::READ_LIST])
                    ->path('/posts');

            case User::class:
                return $defB
                    ->operations([OP::READ, OP::READ_LIST])
                    ->path('/users')
                    ->filterPolicy(SyncFilterPolicy::IGNORE);
        }

        return $defB;
    }

    public function getPosts(SyncContext $ctx): iterable
    {
        $filter = $ctx->getFilters();
        if ($user = $filter['user'] ?? null) {
            return Post::provideList($this->getCurler("/users/$user/posts")->get(), $this, ArrayKeyConformity::NONE, $ctx);
        }

        return Post::provideList($this->getCurler('/posts')->get(), $this, ArrayKeyConformity::NONE, $ctx);
    }
}

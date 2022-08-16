<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Container\AppContainer;
use Lkrms\Container\Container;

/**
 * A facade for AppContainer
 *
 * @method static AppContainer load(?string $basePath = null)
 * @method static $this bind(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null, array $customRule = [])
 * @method static void bindContainer(Container $container)
 * @method static $this enableCache()
 * @method static mixed get(string $id, mixed ...$params)
 * @method static Container getGlobal()
 * @method static bool has(string $id)
 * @method static bool hasCacheStore()
 * @method static bool hasGlobal()
 * @method static string name(string $id)
 * @method static $this pop()
 * @method static $this push()
 * @method static $this singleton(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null, array $customRule = [])
 *
 * @uses AppContainer
 * @lkrms-generate-command lk-util generate facade --class='Lkrms\Container\AppContainer' --generate='Lkrms\Facade\App'
 */
final class App extends Facade
{
    protected static function getServiceName(): string
    {
        return AppContainer::class;
    }
}

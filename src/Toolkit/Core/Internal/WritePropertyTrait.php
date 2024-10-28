<?php declare(strict_types=1);

namespace Salient\Core\Internal;

use Salient\Core\Introspector as IS;

/**
 * @internal
 */
trait WritePropertyTrait
{
    /**
     * @inheritDoc
     */
    public function __set(string $name, $value): void
    {
        $this->writeProperty('set', $name, $value);
    }

    /**
     * @inheritDoc
     */
    public function __unset(string $name): void
    {
        $this->writeProperty('unset', $name);
    }

    /**
     * @param mixed ...$params
     */
    private function writeProperty(string $action, string $name, ...$params): void
    {
        IS::get(static::class)->getPropertyActionClosure($name, $action)($this, ...$params);
    }
}
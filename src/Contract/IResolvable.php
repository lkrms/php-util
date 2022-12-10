<?php declare(strict_types=1);

namespace Lkrms\Contract;

use Closure;

/**
 * Normalises property names
 *
 */
interface IResolvable
{
    /**
     * Return a closure to normalise property names
     *
     * Inheritors may return closures that ignore arguments after `$name`.
     *
     * @psalm-return Closure(string, bool=, string...): string
     * @return Closure
     * ```php
     * function (string $name, bool $aggressive = true, string ...$hints): string
     * ```
     */
    public static function normaliser(): Closure;

    /**
     * Normalise a property name
     *
     * Inheritors should use the closure returned by
     * {@see IResolvable::normaliser()} and may ignore arguments after `$name`.
     *
     */
    public static function normalise(string $name, bool $aggressive = true, string ...$hints): string;
}

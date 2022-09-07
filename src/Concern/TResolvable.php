<?php

declare(strict_types=1);

namespace Lkrms\Concern;

use Closure;
use Lkrms\Facade\Convert;

/**
 * Implements IResolvable to normalise property names
 *
 * @see \Lkrms\Contract\IResolvable
 */
trait TResolvable
{
    public static function getPropertyNormaliser(): Closure
    {
        return fn(string $name) => Convert::toSnakeCase($name);
    }
}

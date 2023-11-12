<?php declare(strict_types=1);

namespace Lkrms\Tests\Support\Introspector;

use Lkrms\Contract\IResolvable;
use Lkrms\Utility\Convert;

class ResolvableB implements IResolvable
{
    public static function normalise(
        string $name,
        bool $greedy = true,
        string ...$hints
    ): string {
        return strtoupper(Convert::toKebabCase($name));
    }
}

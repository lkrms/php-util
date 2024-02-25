<?php declare(strict_types=1);

namespace Lkrms\Tests\Support\Introspector;

use Salient\Core\Contract\IResolvable;
use Salient\Core\Utility\Str;

class ResolvableB implements IResolvable
{
    public static function normalise(
        string $name,
        bool $greedy = true,
        string ...$hints
    ): string {
        return Str::upper(Str::toKebabCase($name));
    }
}

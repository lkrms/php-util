<?php declare(strict_types=1);

namespace Salient\Tests\Core\Introspector;

use Salient\Contract\Core\Entity\Normalisable;
use Salient\Utility\Str;

class ResolvableB implements Normalisable
{
    public static function normaliseProperty(
        string $name,
        bool $greedy = true,
        string ...$hints
    ): string {
        return Str::upper(Str::kebab($name));
    }
}

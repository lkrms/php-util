<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Utility\Computations;

/**
 * A facade for \Lkrms\Utility\Computations
 *
 * @method static Computations load() Load and return an instance of the underlying Computations class
 * @method static Computations getInstance() Return the underlying Computations instance
 * @method static bool isLoaded() Return true if an underlying Computations instance has been loaded
 * @method static void unload() Clear the underlying Computations instance
 * @method static string closureHash(callable $closure) Generate a hash that uniquely identifies a Closure (or any other callable) (see {@see Computations::closureHash()})
 * @method static string hash(mixed ...$value) Generate a unique non-crypto hash (see {@see Computations::hash()})
 * @method static float textDistance(string $string1, string $string2, bool $normalise = true) Returns the Levenshtein distance between two strings relative to the length of the longest string (see {@see Computations::textDistance()})
 * @method static float textSimilarity(string $string1, string $string2, bool $normalise = true) Returns the similarity of two strings relative to the length of the longest string (see {@see Computations::textSimilarity()})
 * @method static string uuid() Generate a cryptographically secure random UUID (see {@see Computations::uuid()})
 *
 * @uses Computations
 * @lkrms-generate-command lk-util generate facade --class='Lkrms\Utility\Computations' --generate='Lkrms\Facade\Compute'
 */
final class Compute extends Facade
{
    /**
     * @internal
     */
    protected static function getServiceName(): string
    {
        return Computations::class;
    }
}

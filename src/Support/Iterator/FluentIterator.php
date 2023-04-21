<?php declare(strict_types=1);

namespace Lkrms\Support\Iterator;

use Iterator;
use IteratorIterator;
use Lkrms\Support\Iterator\Concern\FluentIteratorTrait;
use Lkrms\Support\Iterator\Contract\FluentIteratorInterface;

/**
 * Uses a fluent interface to iterate over another iterator
 *
 * @template TKey of int|string
 * @template TValue
 * @extends IteratorIterator<TKey,TValue,Iterator<TKey,TValue>>
 * @implements FluentIteratorInterface<TKey,TValue>
 */
final class FluentIterator extends IteratorIterator implements FluentIteratorInterface
{
    /**
     * @use FluentIteratorTrait<TKey,TValue>
     */
    use FluentIteratorTrait;

    /**
     * @template T0 of int|string
     * @template T1
     * @param Iterator<T0,T1> $iterator
     * @return self<T0,T1>
     */
    public static function from(Iterator $iterator): self
    {
        /** @var self<T0,T1> */
        $instance = new self($iterator);

        return $instance;
    }
}
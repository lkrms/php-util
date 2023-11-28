<?php declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * @template TKey of array-key
 * @template TValue
 */
interface Arrayable
{
    /**
     * Get the object as an array
     *
     * @return array<TKey,TValue>
     */
    public function toArray(): array;
}

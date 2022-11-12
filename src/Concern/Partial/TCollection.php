<?php

declare(strict_types=1);

namespace Lkrms\Concern\Partial;

// - PHP 8.0 added the `mixed` type
// - PHP 8.1 enforced return type compatibility with built-in interfaces
// - `Iterator` and `ArrayAccess` have methods with return type `mixed`
// - PHP 9.0 is expected to ignore the interim ReturnTypeWillChange attribute
//
// TL;DR: some of PHP's built-in interfaces can't be implemented in
// backward-compatible code without some of it being version-specific
if (PHP_VERSION_ID < 80000)
{
    /**
     * @internal
     */
    trait TCollection
    {
        // Partial implementation of `Iterator`:

        /**
         * @return mixed|false
         */
        final public function current()
        {
            return current($this->_Items);
        }

        /**
         * @return int|string|null
         */
        final public function key()
        {
            return key($this->_Items);
        }

        // Partial implementation of `ArrayAccess`:

        final public function offsetGet($offset)
        {
            return $this->_Items[$offset];
        }
    }
}
else
{
    /**
     * A partial implementation of Iterator and ArrayAccess for PHP 8+
     *
     * On PHP <=7.4, an alternate trait with undeclared return types is loaded
     * instead.
     *
     * For internal use only. Use {@see \Lkrms\Concern\TCollection}.
     */
    trait TCollection
    {
        // Partial implementation of `Iterator`:

        /**
         * @return mixed|false
         */
        final public function current(): mixed
        {
            return current($this->_Items);
        }

        /**
         * @return int|string|null
         */
        final public function key(): mixed
        {
            return key($this->_Items);
        }

        // Partial implementation of `ArrayAccess`:

        final public function offsetGet($offset): mixed
        {
            return $this->_Items[$offset];
        }
    }
}

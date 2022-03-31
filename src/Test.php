<?php

declare(strict_types=1);

namespace Lkrms;

use Exception;

/**
 * Test a value against another value
 *
 * @package Lkrms
 */
class Test
{
    /**
     * Check if a flag is set in a bitmask
     *
     * If `$mask` is not set, returns `true` if bits set in `$flag` are also set
     * in `$value`.
     *
     * If `$mask` is set, returns `true` if masked bits in `$flag` and `$value`
     * have the same state.
     *
     * @param int $value The bitmask being checked.
     * @param int $flag The value of the flag.
     * @param null|int $mask The mask being applied to `$value` and `$flag`.
     * @return bool
     */
    public static function isFlagSet(int $value, int $flag, ?int $mask = null): bool
    {
        return ($value & ($mask ?? $flag)) === $flag;
    }

    /**
     * Check if only one flag is set in a bitmask
     *
     * Returns `true` if exactly one of the masked bits in `$value` is set.
     *
     * @param int $value The bitmask being checked.
     * @param int $mask The mask being applied to `$value`.
     * @return bool
     */
    public static function isOneFlagSet(int $value, int $mask): bool
    {
        return substr_count(decbin($value & $mask), "1") === 1;
    }

    /**
     * Return true for arrays with consecutive integer keys numbered from 0
     *
     * @param mixed $value
     * @return bool
     */
    public static function isListArray($value): bool
    {
        return is_array($value) &&
            (empty($value) ||
                array_keys($value) === range(0, count($value) - 1));
    }

    /**
     * Return true for arrays with at least one string key
     *
     * @param mixed $value
     * @return bool
     */
    public static function isAssociativeArray($value): bool
    {
        return is_array($value) &&
            count(array_filter(array_keys($value), "is_string")) > 0;
    }

    /**
     * Return true for non-empty arrays with no string keys
     *
     * @param mixed $value
     * @return bool
     */
    public static function isIndexedArray($value): bool
    {
        return is_array($value) &&
            !empty($value) && !self::isAssociativeArray($value);
    }

    /**
     * Return true if a stream is backed by the same resource as another
     *
     * @param resource $value
     * @param resource $stream
     * @return bool
     */
    public static function isSameStream($value, $stream): bool
    {
        try
        {
            $meta       = stream_get_meta_data($value);
            $streamMeta = stream_get_meta_data($stream);
        }
        catch (Exception $ex)
        {
            unset($ex);

            return false;
        }

        return $streamMeta['uri'] === $meta['uri'];
    }
}


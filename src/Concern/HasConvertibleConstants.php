<?php declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Concept\ConvertibleEnumeration;
use Lkrms\Contract\IConvertibleEnumeration;
use LogicException;

/**
 * Uses arrays provided by the class to map its public constants to and from
 * their names
 *
 * @template TValue
 *
 * @see IConvertibleEnumeration Implemented by this trait.
 * @see ConvertibleEnumeration An abstract class that provides an alternative
 * implementation using reflection.
 */
trait HasConvertibleConstants
{
    /**
     * Get an array that maps values to names
     *
     * @return array<TValue,string> Value => name
     */
    abstract protected static function getNameMap(): array;

    /**
     * Get an array that maps uppercase names to values
     *
     * @return array<string,TValue> UPPERCASE NAME => value
     */
    abstract protected static function getValueMap(): array;

    /**
     * Class name => [ constant name => value ]
     *
     * @var array<string,array<string,TValue>>
     */
    private static $ValueMaps = [];

    /**
     * Class name => [ constant value => name ]
     *
     * @var array<string,array<TValue,string>>
     */
    private static $NameMaps = [];

    /**
     * @return TValue
     */
    public static function fromName(string $name)
    {
        if ((self::$ValueMaps[static::class] ?? null) === null) {
            self::$ValueMaps[static::class] = static::getValueMap();
        }
        if (($value = self::$ValueMaps[static::class][$name]
                ?? self::$ValueMaps[static::class][strtoupper($name)]
                ?? null) === null) {
            throw new LogicException(
                sprintf('Argument #1 ($name) is invalid: %s', $name)
            );
        }
        return $value;
    }

    /**
     * @param TValue $value
     */
    public static function toName($value): string
    {
        if ((self::$NameMaps[static::class] ?? null) === null) {
            self::$NameMaps[static::class] = static::getNameMap();
        }
        if (($name = self::$NameMaps[static::class][$value] ?? null) === null) {
            throw new LogicException(
                sprintf('Argument #1 ($value) is invalid: %s', $value)
            );
        }
        return $name;
    }
}

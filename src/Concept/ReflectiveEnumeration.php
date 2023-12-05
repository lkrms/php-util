<?php declare(strict_types=1);

namespace Lkrms\Concept;

use Lkrms\Concern\IsConvertibleEnumeration;
use Lkrms\Contract\IConvertibleEnumeration;
use LogicException;
use ReflectionClass;

/**
 * Base class for enumerations that use reflection to convert constants to and
 * from their names
 *
 * @template TValue of array-key
 *
 * @extends Enumeration<TValue>
 * @implements IConvertibleEnumeration<TValue>
 */
abstract class ReflectiveEnumeration extends Enumeration implements IConvertibleEnumeration
{
    /** @use IsConvertibleEnumeration<TValue> */
    use IsConvertibleEnumeration;

    /**
     * Class name => [ constant value => name ]
     *
     * @var array<string,array<TValue,string>>
     */
    private static $NameMaps = [];

    /**
     * Class name => [ constant name => value ]
     *
     * @var array<string,array<string,TValue>>
     */
    private static $ValueMaps = [];

    private static function loadMaps(): void
    {
        $constants = (new ReflectionClass(static::class))->getReflectionConstants();
        $valueMap = [];
        $nameMap = [];
        foreach ($constants as $constant) {
            if (!$constant->isPublic()) {
                continue;
            }
            $name = $constant->getName();
            $value = $constant->getValue();
            $valueMap[$name] = $value;
            $nameMap[$value] = $name;
        }
        if (!$valueMap) {
            self::$ValueMaps[static::class] = [];
            self::$NameMaps[static::class] = [];
            return;
        }
        if (count($valueMap) !== count($nameMap)) {
            throw new LogicException(
                sprintf('Public constants do not have unique values: %s', static::class)
            );
        }
        // Add UPPER_CASE names to $valueMap if not already present
        $valueMap += array_combine(array_map('strtoupper', array_keys($valueMap)), $valueMap);
        self::$ValueMaps[static::class] = $valueMap;
        self::$NameMaps[static::class] = $nameMap;
    }

    /**
     * @inheritDoc
     */
    final public static function fromName(string $name)
    {
        if (!isset(self::$ValueMaps[static::class])) {
            self::loadMaps();
        }
        $value = self::$ValueMaps[static::class][$name]
            ?? self::$ValueMaps[static::class][strtoupper($name)]
            ?? null;
        if ($value === null) {
            throw new LogicException(
                sprintf('Argument #1 ($name) is invalid: %s', $name)
            );
        }
        return $value;
    }

    /**
     * @inheritDoc
     */
    final public static function toName($value): string
    {
        if (!isset(self::$NameMaps[static::class])) {
            self::loadMaps();
        }
        $name = self::$NameMaps[static::class][$value] ?? null;
        if ($name === null) {
            throw new LogicException(
                sprintf('Argument #1 ($value) is invalid: %s', $value)
            );
        }
        return $name;
    }

    /**
     * @inheritDoc
     */
    final public static function cases(): array
    {
        if (!isset(self::$ValueMaps[static::class])) {
            self::loadMaps();
        }
        return self::$ValueMaps[static::class];
    }
}

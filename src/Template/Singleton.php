<?php

declare(strict_types=1);

namespace Lkrms\Template;

/**
 * An abstract singleton class
 *
 * @package Lkrms
 * @see SingletonTrait
 */
abstract class Singleton
{
    private static $Singletons = [];

    final protected function __construct()
    {
        self::$Singletons[ static::class] = $this;
    }

    final protected static function HasInstance(): bool
    {
        return !is_null(self::$Singletons[ static::class] ?? null);
    }

    /**
     *
     * @return static
     */
    public static function GetInstance()
    {
        return (self::$Singletons[ static::class] ?? null) ?: new static();
    }
}


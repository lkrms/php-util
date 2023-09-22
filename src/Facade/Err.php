<?php declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Support\ErrorHandler;

/**
 * A facade for \Lkrms\Support\ErrorHandler
 *
 * @method static ErrorHandler load() Load and return an instance of the underlying ErrorHandler class
 * @method static ErrorHandler getInstance() Get the underlying ErrorHandler instance
 * @method static bool isLoaded() True if an underlying ErrorHandler instance has been loaded
 * @method static void unload() Clear the underlying ErrorHandler instance
 * @method static ErrorHandler deregister() Deregister previously registered error and exception handlers
 * @method static ErrorHandler register() Register error, exception and shutdown handlers
 * @method static ErrorHandler silencePath(string $path, int $levels = 26624) Silence errors in a file or directory
 * @method static ErrorHandler silencePattern(string $pattern, int $levels = 26624) Silence errors in paths that match a regular expression
 *
 * @uses ErrorHandler
 *
 * @extends Facade<ErrorHandler>
 */
final class Err extends Facade
{
    /**
     * @internal
     */
    protected static function getServiceName(): string
    {
        return ErrorHandler::class;
    }
}

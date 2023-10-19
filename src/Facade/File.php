<?php declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Iterator\RecursiveFilesystemIterator;
use Lkrms\Utility\Filesystem;
use SplFileInfo;

/**
 * A facade for \Lkrms\Utility\Filesystem
 *
 * @method static Filesystem load() Load and return an instance of the underlying Filesystem class
 * @method static Filesystem getInstance() Get the underlying Filesystem instance
 * @method static bool isLoaded() True if an underlying Filesystem instance has been loaded
 * @method static void unload() Clear the underlying Filesystem instance
 * @method static void close(resource $handle, string $filename) Close an open file or URL (see {@see Filesystem::close()})
 * @method static string createTemporaryDirectory() Create a temporary directory
 * @method static RecursiveFilesystemIterator find(string[]|string|null $directory = null, ?string $exclude = null, ?string $include = null, array<string,callable(SplFileInfo): bool> $excludeCallbacks = null, array<string,callable(SplFileInfo): bool> $includeCallbacks = null, bool $recursive = true, bool $withDirectories = false, bool $withDirectoriesFirst = true) Iterate over files in one or more directories
 * @method static string|null getEol(string $filename) Get the end-of-line sequence used in a file (see {@see Filesystem::getEol()})
 * @method static string getStablePath(string $suffix = '', ?string $dir = null) Generate a filename unique to the current user and the path of the running script (see {@see Filesystem::getStablePath()})
 * @method static string|null getStreamUri(resource $stream) Get the URI or filename associated with a stream (see {@see Filesystem::getStreamUri()})
 * @method static bool isPhp(string $filename) True if a file appears to contain PHP code
 * @method static bool maybeCreate(string $filename, int $permissions = 511, int $dirPermissions = 511) Create a file if it doesn't exist (see {@see Filesystem::maybeCreate()})
 * @method static bool maybeCreateDirectory(string $directory, int $permissions = 511) Create a directory if it doesn't exist (see {@see Filesystem::maybeCreateDirectory()})
 * @method static bool maybeDelete(string $filename) Delete a file if it exists (see {@see Filesystem::maybeDelete()})
 * @method static bool maybeDeleteDirectory(string $directory, bool $recursive = false) Delete a directory if it exists (see {@see Filesystem::maybeDeleteDirectory()})
 * @method static resource open(string $filename, string $mode) Open a file or URL (see {@see Filesystem::open()})
 * @method static bool pruneDirectory(string $directory) Recursively delete the contents of a directory without deleting the directory itself (see {@see Filesystem::pruneDirectory()})
 * @method static string|false realpath(string $filename) A Phar-friendly, file descriptor-aware realpath() (see {@see Filesystem::realpath()})
 *
 * @uses Filesystem
 *
 * @extends Facade<Filesystem>
 */
final class File extends Facade
{
    /**
     * @internal
     */
    protected static function getServiceName(): string
    {
        return Filesystem::class;
    }

    /**
     * Write data to a CSV file or stream
     *
     * @param iterable<mixed[]> $data
     * @param string|resource|null $target
     * @param int|string|null $nullValue
     * @param callable(mixed): mixed[] $callback
     * @param int|null $count
     * @return string|true
     * @see Filesystem::writeCsv()
     */
    public static function writeCsv(iterable $data, $target = null, bool $headerRow = true, $nullValue = null, ?callable $callback = null, ?int &$count = null, string $eol = "\r\n", bool $utf16le = true, bool $bom = true)
    {
        return static::getInstance()->writeCsv($data, $target, $headerRow, $nullValue, $callback, $count, $eol, $utf16le, $bom);
    }
}

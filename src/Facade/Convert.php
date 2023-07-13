<?php declare(strict_types=1);

namespace Lkrms\Facade;

use ArrayAccess;
use Closure;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Iterator;
use Lkrms\Concept\Facade;
use Lkrms\Support\DateFormatter;
use Lkrms\Support\Iterator\Contract\MutableIterator;
use Lkrms\Utility\Conversions;
use RecursiveIterator;

/**
 * A facade for \Lkrms\Utility\Conversions
 *
 * @method static Conversions load() Load and return an instance of the underlying Conversions class
 * @method static Conversions getInstance() Get the underlying Conversions instance
 * @method static bool isLoaded() True if an underlying Conversions instance has been loaded
 * @method static void unload() Clear the underlying Conversions instance
 * @method static int|null arrayKeyToOffset(string|int $key, array $array) Get the offset of a key in an array (see {@see Conversions::arrayKeyToOffset()})
 * @method static string classToBasename(string $class, string ...$suffixes) Remove the namespace and the first matched suffix from a class name
 * @method static string classToNamespace(string $class) Get the namespace of a class (see {@see Conversions::classToNamespace()})
 * @method static mixed coalesce(...$values) Get the first value that is not null
 * @method static string dataToQuery(array $data, bool $preserveKeys = false, ?DateFormatter $dateFormatter = null) A more API-friendly http_build_query (see {@see Conversions::dataToQuery()})
 * @method static string ellipsize(string $value, int $length) Replace the end of a multi-byte string with an ellipsis ("...") if its length exceeds a limit
 * @method static mixed emptyToNull($value) If a value is 'falsey', make it null (see {@see Conversions::emptyToNull()})
 * @method static string expandTabs(string $text, int $tabSize, int $column = 1) Expand tabs to spaces
 * @method static mixed flatten($value) Recursively remove outer single-element arrays (see {@see Conversions::flatten()})
 * @method static int intervalToSeconds(DateInterval|string $value) Convert an interval to the equivalent number of seconds (see {@see Conversions::intervalToSeconds()})
 * @method static array iterableToArray(iterable $iterable, bool $preserveKeys = false) If an iterable isn't already an array, make it one
 * @method static array|object|false iterableToItem(iterable $list, int|string|Closure $key, $value, bool $strict = false) Get the first item in $list where the value at $key is $value (see {@see Conversions::iterableToItem()})
 * @method static Iterator iterableToIterator(iterable $iterable) If an iterable isn't already an Iterator, enclose it in one
 * @method static string lineEndingsToUnix(string $string) Replace a string's CRLF or CR end-of-line sequences with LF
 * @method static string linesToLists(string $text, string $separator = "\n", string|null $marker = null, string $regex = '/^\\h*[-*] /', bool $clean = false) Remove duplicates in a string where 'top-level' lines ("section names") are grouped with any subsequent 'child' lines ("list items") (see {@see Conversions::linesToLists()})
 * @method static array listToMap(array $list, int|string|Closure $key) Create a map from a list (see {@see Conversions::listToMap()})
 * @method static string methodToFunction(string $method) Remove the class from a method name
 * @method static string nounToPlural(string $noun) Get the plural of a singular noun
 * @method static array objectToArray(object $object) A wrapper for get_object_vars (see {@see Conversions::objectToArray()})
 * @method static array|false parseUrl(string $url) Parse a URL and return its components, including "params" if FTP parameters are present (see {@see Conversions::parseUrl()})
 * @method static string pathToBasename(string $path, int $extLimit = 0) Remove the directory and up to the given number of extensions from a path (see {@see Conversions::pathToBasename()})
 * @method static string plural(int $number, string $singular, string|null $plural = null, bool $includeNumber = false) If $number is 1, return $singular, otherwise return $plural (see {@see Conversions::plural()})
 * @method static string pluralRange(int $from, int $to, string $singular, string|null $plural = null, string $preposition = 'on') Get a phrase like "between lines 3 and 11" or "on platform 23" (see {@see Conversions::pluralRange()})
 * @method static array<string,string> queryToData(string[] $query) Convert a list of "key=value" strings to an array like ["key" => "value"]
 * @method static array renameArrayKey(string|int $key, string|int $newKey, array $array) Rename an array key without changing the order of values in the array
 * @method static string resolvePath(string $path) Resolve relative segments in a pathname (see {@see Conversions::resolvePath()})
 * @method static string resolveRelativeUrl(string $embeddedUrl, string $baseUrl) Get the absolute form of a URL relative to a base URL, as per [RFC1808]
 * @method static string|false scalarToString($value) Convert a scalar to a string (see {@see Conversions::scalarToString()})
 * @method static int sizeToBytes(string $size) Convert php.ini values like "128M" to bytes (see {@see Conversions::sizeToBytes()})
 * @method static string sparseToString(string $separator, array $array) Remove zero-width values from an array before imploding it
 * @method static string[] splitAndTrim(string $separator, string $string, string|null $characters = null) Split a string by a string, remove whitespace from the beginning and end of each substring, remove empty strings (see {@see Conversions::splitAndTrim()})
 * @method static string[] splitAndTrimOutsideBrackets(string $separator, string $string, string|null $characters = null) Split a string by a string without separating substrings enclosed by brackets, remove whitespace from the beginning and end of each substring, remove empty strings (see {@see Conversions::splitAndTrimOutsideBrackets()})
 * @method static string[] splitOutsideBrackets(string $separator, string $string) Split a string by a string without separating substrings enclosed by brackets
 * @method static array<array-key,string> stringsToUnique(array<array-key,string> $array) A faster array_unique
 * @method static string[] stringsToUniqueList(string[] $array) A faster array_unique with reindexing
 * @method static array toArray($value, bool $emptyIfNull = false) If a value isn't an array, make it the first element of one (see {@see Conversions::toArray()})
 * @method static bool|null toBoolOrNull($value) Cast a value to a boolean, preserving null and converting boolean strings (see {@see Conversions::toBoolOrNull()})
 * @method static string toCamelCase(string $text, ?string $preserve = null) Convert an identifier to camelCase
 * @method static DateTimeImmutable toDateTimeImmutable(DateTimeInterface $date) A shim for DateTimeImmutable::createFromInterface() (PHP 8+)
 * @method static int|null toIntOrNull($value) Cast a value to an integer, preserving null
 * @method static string toKebabCase(string $text, ?string $preserve = null) Convert an identifier to kebab-case
 * @method static array toList($value, bool $emptyIfNull = false) If a value isn't a list, make it the first element of one (see {@see Conversions::toList()})
 * @method static string toNormal(string $text) Clean up a string for comparison with other strings (see {@see Conversions::toNormal()})
 * @method static string toPascalCase(string $text, ?string $preserve = null) Convert an identifier to PascalCase
 * @method static array<int,int|float|string|bool|null> toScalarArray(array $array) JSON-encode non-scalar values in an array
 * @method static string toShellArg(string $value) A platform-agnostic escapeshellarg that only adds quotes if necessary
 * @method static string toSnakeCase(string $text, ?string $preserve = null) Convert an identifier to snake_case
 * @method static string[] toStrings(...$value) Convert the given strings and Stringables to an array of strings
 * @method static DateTimeZone toTimezone(DateTimeZone|string $value) Convert a value to a DateTimeZone instance
 * @method static array<array-key,mixed> toUnique(array<array-key,mixed> $array) A type-agnostic array_unique
 * @method static mixed[] toUniqueList(mixed[] $array) A type-agnostic array_unique with reindexing
 * @method static string unparseUrl(array<string,string|int> $url) Convert a parse_url array to a string (see {@see Conversions::unparseUrl()})
 * @method static string unwrap(string $string, string $break = "\n") Undo wordwrap(), preserving line breaks that appear consecutively, immediately after 2 spaces, or immediately before 4 spaces
 * @method static string uuidToHex(string $bytes) Convert a 16-byte UUID to its 36-byte hexadecimal representation
 * @method static mixed valueAtKey(array|ArrayAccess|object $item, int|string $key) Get the value at $key in $item, where $item is an array or object
 * @method static string valueToCode($value, string $delimiter = "\054 ", string $arrow = ' => ', ?string $escapeCharacters = null) Like var_export but with more compact output
 *
 * @uses Conversions
 *
 * @extends Facade<Conversions>
 */
final class Convert extends Facade
{
    /**
     * @internal
     */
    protected static function getServiceName(): string
    {
        return Conversions::class;
    }

    /**
     * array_splice for associative arrays
     *
     * @param string|int $key
     * @see Conversions::arraySpliceAtKey()
     */
    public static function arraySpliceAtKey(array &$array, $key, ?int $length = null, array $replacement = []): array
    {
        return static::getInstance()->arraySpliceAtKey($array, $key, $length, $replacement);
    }

    /**
     * A type-agnostic multi-column array_unique
     *
     * @param array<array-key,mixed> $array
     * @return array<array-key,mixed>
     * @see Conversions::columnsToUnique()
     */
    public static function columnsToUnique(array $array, array &...$columns): array
    {
        return static::getInstance()->columnsToUnique($array, ...$columns);
    }

    /**
     * A type-agnostic multi-column array_unique with reindexing
     *
     * @param mixed[] $array
     * @return mixed[]
     * @see Conversions::columnsToUniqueList()
     */
    public static function columnsToUniqueList(array $array, array &...$columns): array
    {
        return static::getInstance()->columnsToUniqueList($array, ...$columns);
    }

    /**
     * array_walk_recursive for arbitrarily nested objects and arrays
     *
     * @param object|mixed[] $objectOrArray
     * @param callable(mixed, array-key, MutableIterator<array-key,mixed>&RecursiveIterator<array-key,mixed>): bool $callback
     * @see Conversions::walkRecursive()
     */
    public static function walkRecursive(&$objectOrArray, callable $callback, int $mode = \RecursiveIteratorIterator::LEAVES_ONLY): void
    {
        static::getInstance()->walkRecursive($objectOrArray, $callback, $mode);
    }
}

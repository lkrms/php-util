<?php declare(strict_types=1);

namespace Lkrms\Utility;

use Lkrms\Concept\Utility;
use Lkrms\Exception\InvalidArgumentException;
use Lkrms\Support\Catalog\CharacterSequence as Char;
use Lkrms\Support\Catalog\RegularExpression as Regex;

/**
 * Manipulate strings
 */
final class Str extends Utility
{
    /**
     * Get the first string that is not null or empty, or return the last value
     */
    public static function coalesce(?string $string, ?string ...$strings): ?string
    {
        array_unshift($strings, $string);
        $last = array_pop($strings);
        foreach ($strings as $string) {
            if ($string === null || $string === '') {
                continue;
            }
            return $string;
        }
        return $last;
    }

    /**
     * Convert ASCII alphabetic characters in a string to lowercase
     */
    public static function lower(string $string): string
    {
        return strtr($string, Char::ALPHABETIC_UPPER, Char::ALPHABETIC_LOWER);
    }

    /**
     * Convert ASCII alphabetic characters in a string to uppercase
     */
    public static function upper(string $string): string
    {
        return strtr($string, Char::ALPHABETIC_LOWER, Char::ALPHABETIC_UPPER);
    }

    /**
     * If the first character in a string is an ASCII alphabetic character, make
     * it uppercase
     */
    public static function upperFirst(string $string): string
    {
        if ($string === '') {
            return $string;
        }
        $string[0] = self::upper($string[0]);
        return $string;
    }

    /**
     * Match an ASCII string's case to another string
     */
    public static function matchCase(string $string, string $match): string
    {
        $match = trim($match);

        if ($match === '') {
            return $string;
        }

        $upper = strpbrk($match, Char::ALPHABETIC_UPPER);
        $hasUpper = $upper !== false;
        $hasLower = strpbrk($match, Char::ALPHABETIC_LOWER) !== false;

        if ($hasUpper && !$hasLower && strlen($match) > 1) {
            return self::upper($string);
        }

        if (!$hasUpper && $hasLower) {
            return self::lower($string);
        }

        if (
            // @phpstan-ignore-next-line
            (!$hasUpper && !$hasLower) ||
            $upper !== $match
        ) {
            return $string;
        }

        return self::upperFirst(self::lower($string));
    }

    /**
     * Replace the end of a string with an ellipsis ("...") if its length
     * exceeds a limit
     */
    public static function ellipsize(string $value, int $length): string
    {
        if ($length < 3) {
            $length = 3;
        }
        if (mb_strlen($value) > $length) {
            return rtrim(mb_substr($value, 0, $length - 3)) . '...';
        }

        return $value;
    }

    /**
     * Apply an end-of-line sequence to a string
     */
    public static function setEol(string $string, string $eol = "\n"): string
    {
        switch ($eol) {
            case "\n":
                return str_replace(["\r\n", "\r"], $eol, $string);

            case "\r":
                return str_replace(["\r\n", "\n"], $eol, $string);

            case "\r\n":
                return str_replace(["\r\n", "\r", "\n"], ["\n", "\n", $eol], $string);

            default:
                return str_replace("\n", $eol, self::setEol($string));
        }
    }

    /**
     * Replace newlines in a string with native end-of-line sequences
     *
     * @template T of string|null
     *
     * @param T $string
     * @return T
     */
    public static function eolToNative(?string $string): ?string
    {
        return $string === null
            ? null
            : (\PHP_EOL === "\n"
                ? $string
                : str_replace("\n", \PHP_EOL, $string));
    }

    /**
     * Replace native end-of-line sequences in a string with newlines
     *
     * @template T of string|null
     *
     * @param T $string
     * @return T
     */
    public static function eolFromNative(?string $string): ?string
    {
        return $string === null
            ? null
            : (\PHP_EOL === "\n"
                ? $string
                : str_replace(\PHP_EOL, "\n", $string));
    }

    /**
     * Convert words in an arbitrarily capitalised string to snake_case,
     * optionally preserving given characters
     *
     * @see Str::toWords()
     */
    public static function toSnakeCase(string $string, ?string $preserve = null): string
    {
        return self::lower(self::toWords($string, '_', $preserve));
    }

    /**
     * Convert words in an arbitrarily capitalised string to kebab-case,
     * optionally preserving given characters
     *
     * @see Str::toWords()
     */
    public static function toKebabCase(string $string, ?string $preserve = null): string
    {
        return self::lower(self::toWords($string, '-', $preserve));
    }

    /**
     * Convert words in an arbitrarily capitalised string to camelCase,
     * optionally preserving given characters
     *
     * @see Str::toWords()
     */
    public static function toCamelCase(string $string, ?string $preserve = null): string
    {
        return Pcre::replaceCallback(
            '/(?<![[:alnum:]])[[:alpha:]]/u',
            fn($matches) => self::lower($matches[0]),
            self::toPascalCase($string, $preserve)
        );
    }

    /**
     * Convert words in an arbitrarily capitalised string to PascalCase,
     * optionally preserving given characters
     *
     * @see Str::toWords()
     */
    public static function toPascalCase(string $string, ?string $preserve = null): string
    {
        return self::toWords(
            $string,
            '',
            $preserve,
            fn($word) => self::upperFirst(self::lower($word))
        );
    }

    /**
     * Get the words in an arbitrarily capitalised string and delimit them with
     * a given separator, optionally preserving given characters and applying a
     * callback to each word
     *
     * Words in `$string` may be separated by any combination of
     * non-alphanumeric characters and capitalisation. For example:
     *
     * - `foo bar` => foo bar
     * - `FOO_BAR` => FOO BAR
     * - `fooBar` => foo Bar
     * - `$this = fooBar` => this foo Bar
     * - `PHPDoc` => PHP Doc
     *
     * This method forms the basis of capitalisation methods.
     *
     * @param string|null $preserve Characters to keep in the string.
     * Alphanumeric characters are always preserved.
     * @param (callable(string): string)|null $callback
     */
    public static function toWords(
        string $string,
        string $separator = ' ',
        ?string $preserve = null,
        ?callable $callback = null
    ): string {
        $notAfterPreserve = '';
        if ((string) $preserve !== '') {
            $preserve = Pcre::replace('/[[:alnum:]]/u', '', (string) $preserve);
            if ($preserve !== '') {
                // Prevent "key=value" becoming "key= value" when preserving "="
                // by asserting that when separating words, they must appear:
                // - immediately after the previous word (\G)
                // - after an unpreserved character, or
                // - at a word boundary (e.g. "Value" in "key=someValue")
                $preserve = Pcre::quoteCharacterClass($preserve, '/');
                $notAfterPreserve = "(?:\G|(?<=[^[:alnum:]{$preserve}])|(?<=[[:lower:][:digit:]])(?=[[:upper:]]))";
            }
        }
        $preserve = "[:alnum:]{$preserve}";
        $word = '(?:[[:upper:]]?[[:lower:][:digit:]]+|(?:[[:upper:]](?![[:lower:]]))+[[:digit:]]*)';

        // Insert separators before words not adjacent to a preserved character
        // to prevent "foo bar" becoming "foobar", for example
        if ($separator !== '') {
            $string = Pcre::replace(
                "/$notAfterPreserve$word/u",
                $separator . '$0',
                $string
            );
        }

        if ($callback !== null) {
            $string = Pcre::replaceCallback(
                "/$word/u",
                fn(array $match): string => $callback($match[0]),
                $string
            );
        }

        // Trim unpreserved characters from the beginning and end of the string,
        // then replace sequences of one or more unpreserved characters with one
        // separator
        $string = Pcre::replace([
            "/^[^{$preserve}]++|[^{$preserve}]++\$/u",
            "/[^{$preserve}]++/u",
        ], [
            '',
            $separator,
        ], $string);

        return $string;
    }

    /**
     * Expand tabs to spaces
     */
    public static function expandTabs(
        string $text,
        int $tabSize = 8,
        int $column = 1
    ): string {
        if (strpos($text, "\t") === false) {
            return $text;
        }
        $eol = Get::eol($text) ?: "\n";
        $expanded = '';
        foreach (explode($eol, $text) as $i => $line) {
            !$i || $expanded .= $eol;
            $parts = explode("\t", $line);
            $last = array_key_last($parts);
            foreach ($parts as $p => $part) {
                $expanded .= $part;
                if ($p === $last) {
                    break;
                }
                $column += mb_strlen($part);
                // e.g. with $tabSize 4, a tab at $column 2 occupies 3 spaces
                $spaces = $tabSize - (($column - 1) % $tabSize);
                $expanded .= str_repeat(' ', $spaces);
                $column += $spaces;
            }
            $column = 1;
        }
        return $expanded;
    }

    /**
     * Expand leading tabs to spaces
     */
    public static function expandLeadingTabs(
        string $text,
        int $tabSize = 8,
        bool $preserveLine1 = false,
        int $column = 1
    ): string {
        if (strpos($text, "\t") === false) {
            return $text;
        }
        $eol = Get::eol($text) ?: "\n";
        $softTab = str_repeat(' ', $tabSize);
        $expanded = '';
        foreach (explode($eol, $text) as $i => $line) {
            !$i || $expanded .= $eol;
            if ($i || (!$preserveLine1 && $column === 1)) {
                $expanded .= Pcre::replace('/(?<=\n|\G)\t/', $softTab, $line);
                continue;
            }
            if ($preserveLine1) {
                $expanded .= $line;
                continue;
            }
            $parts = explode("\t", $line);
            while (($part = array_shift($parts)) !== null) {
                $expanded .= $part;
                if (!$parts) {
                    break;
                }
                if ($part) {
                    $expanded .= "\t" . implode("\t", $parts);
                    break;
                }
                $column += mb_strlen($part);
                $spaces = $tabSize - (($column - 1) % $tabSize);
                $expanded .= str_repeat(' ', $spaces);
                $column += $spaces;
            }
        }
        return $expanded;
    }

    /**
     * Copy a string to a temporary stream
     *
     * @return resource
     */
    public static function toStream(string $string)
    {
        $stream = File::open('php://temp', 'r+');
        File::write($stream, $string);
        File::seek($stream, 0);
        return $stream;
    }

    /**
     * Split a string by a string, remove whitespace from the beginning and end
     * of each substring, remove empty strings
     *
     * @param string|null $characters Optionally specify characters to remove
     * instead of whitespace.
     * @return string[]
     */
    public static function splitAndTrim(string $separator, string $string, ?string $characters = null): array
    {
        return array_values(Arr::trim(
            explode($separator, $string),
            $characters
        ));
    }

    /**
     * Split a string by a string without separating substrings enclosed by
     * brackets, remove whitespace from the beginning and end of each substring,
     * remove empty strings
     *
     * @param string|null $characters Optionally specify characters to remove
     * instead of whitespace.
     * @return string[]
     */
    public static function splitAndTrimOutsideBrackets(string $separator, string $string, ?string $characters = null): array
    {
        return array_values(Arr::trim(
            self::splitOutsideBrackets($separator, $string),
            $characters
        ));
    }

    /**
     * Split a string by a string without separating substrings enclosed by
     * brackets
     *
     * @return string[]
     */
    public static function splitOutsideBrackets(string $separator, string $string): array
    {
        if (strlen($separator) !== 1) {
            throw new InvalidArgumentException('Separator must be a single character');
        }

        if (strpos('()<>[]{}', $separator) !== false) {
            throw new InvalidArgumentException('Separator cannot be a bracket character');
        }

        $quoted = preg_quote($separator, '/');

        $escaped = $separator;
        if (strpos('\-', $separator) !== false) {
            $escaped = '\\' . $separator;
        }

        $regex = <<<REGEX
            (?x)
            (?: [^()<>[\]{}{$escaped}]++ |
              ( \( (?: [^()<>[\]{}]*+ (?-1)? )*+ \) |
                <  (?: [^()<>[\]{}]*+ (?-1)? )*+ >  |
                \[ (?: [^()<>[\]{}]*+ (?-1)? )*+ \] |
                \{ (?: [^()<>[\]{}]*+ (?-1)? )*+ \} ) |
              # Match empty substrings
              (?<= $quoted ) (?= $quoted ) )+
            REGEX;

        Pcre::matchAll(
            Regex::delimit($regex),
            $string,
            $matches,
        );

        return $matches[0];
    }

    /**
     * Wrap a string to a given number of characters, optionally varying the
     * widths of the second and subsequent lines from the first
     *
     * If `$width` is an `array`, the first line of text is wrapped to the first
     * value, and text in subsequent lines is wrapped to the second value.
     *
     * @param array{int,int}|int $width
     */
    public static function wordwrap(
        string $string,
        $width = 75,
        string $break = "\n",
        bool $cutLongWords = false
    ): string {
        [$delta, $width] = is_array($width)
            ? [$width[1] - $width[0], $width[1]]
            : [0, $width];

        if (!$delta) {
            return wordwrap($string, $width, $break, $cutLongWords);
        }

        // For hanging indents, remove and restore the first $delta characters
        if ($delta < 0) {
            return substr($string, 0, -$delta)
                . wordwrap(substr($string, -$delta), $width, $break, $cutLongWords);
        }

        // For first line indents, add and remove $delta characters
        return substr(
            wordwrap(str_repeat('x', $delta) . $string, $width, $break, $cutLongWords),
            $delta
        );
    }

    /**
     * Enclose a string between delimiters
     *
     * @param string|null $after If `null`, `$before` is used before and after
     * the string.
     */
    public static function wrap(string $string, string $before, ?string $after = null): string
    {
        return $before . $string . ($after ?? $before);
    }

    /**
     * Remove duplicates in a string where top-level lines ("sections") are
     * grouped with "list items" below
     *
     * Lines that match `$regex` are regarded as list items, and other lines are
     * used as the section name for subsequent list items. If `$loose` is
     * `false` (the default), blank lines between list items clear the current
     * section name.
     *
     * Top-level lines with no children, including any list items orphaned by
     * blank lines above them, are returned before sections with children.
     *
     * If a named subpattern in `$regex` called `indent` matches a non-empty
     * string, subsequent lines with the same number of spaces for indentation
     * as there are characters in the match are treated as part of the item,
     * including any blank lines.
     *
     * Line endings used in `$text` may be any combination of LF, CRLF and CR,
     * but LF (`"\n"`) line endings are used in the return value.
     *
     * @param string $separator Used between top-level lines and sections. Has
     * no effect on the end-of-line sequence used between items, which is always
     * LF (`"\n"`).
     * @param string|null $marker Added before each section name. Nested list
     * items are indented by the equivalent number of spaces. To add a leading
     * `"- "` to top-level lines and indent others with two spaces, set
     * `$marker` to `"-"`.
     * @param bool $clean If `true`, the first match of `$regex` in each section
     * name is removed.
     * @param bool $loose If `true`, blank lines between list items are ignored.
     */
    public static function mergeLists(
        string $text,
        string $separator = "\n",
        ?string $marker = null,
        string $regex = '/^(?<indent>\h*[-*] )/',
        bool $clean = false,
        bool $loose = false
    ): string {
        $marker = (string) $marker !== '' ? $marker . ' ' : null;
        $indent = $marker !== null ? str_repeat(' ', mb_strlen($marker)) : '';
        $markerIsItem = $marker !== null && Pcre::match($regex, $marker);

        /** @var array<string,string[]> */
        $sections = [];
        $lastWasItem = false;
        $lines = Pcre::split('/\r\n|\n|\r/', $text);
        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];

            // Remove pre-existing markers early to ensure sections with the
            // same name are combined
            if ($marker !== null && !$markerIsItem && strpos($line, $marker) === 0) {
                $line = substr($line, strlen($marker));
            }

            // Treat blank lines between items as section breaks
            if (trim($line) === '') {
                if (!$loose && $lastWasItem) {
                    unset($section);
                }
                continue;
            }

            // Collect any subsequent indented lines
            if (Pcre::match($regex, $line, $matches)) {
                $matchIndent = $matches['indent'] ?? '';
                if ($matchIndent !== '') {
                    $matchIndent = str_repeat(' ', mb_strlen($matchIndent));
                    $pendingWhitespace = '';
                    $backtrack = 0;
                    while ($i < count($lines) - 1) {
                        $nextLine = $lines[$i + 1];
                        if (trim($nextLine) === '') {
                            $pendingWhitespace .= $nextLine . "\n";
                            $backtrack++;
                        } elseif (substr($nextLine, 0, strlen($matchIndent)) === $matchIndent) {
                            $line .= "\n" . $pendingWhitespace . $nextLine;
                            $pendingWhitespace = '';
                            $backtrack = 0;
                        } else {
                            $i -= $backtrack;
                            break;
                        }
                        $i++;
                    }
                }
            } else {
                $section = $line;
            }

            $key = $section ?? $line;

            if (!array_key_exists($key, $sections)) {
                $sections[$key] = [];
            }

            if ($key !== $line) {
                if (!in_array($line, $sections[$key])) {
                    $sections[$key][] = $line;
                }
                $lastWasItem = true;
            } else {
                $lastWasItem = false;
            }
        }

        // Move lines with no associated list to the top
        /** @var array<string,string[]> */
        $top = [];
        $last = null;
        foreach ($sections as $section => $lines) {
            if (count($lines)) {
                continue;
            }

            unset($sections[$section]);

            if ($clean) {
                $top[$section] = [];
                continue;
            }

            // Collect second and subsequent consecutive top-level list items
            // under the first so they don't form a loose list
            if (Pcre::match($regex, $section)) {
                if ($last !== null) {
                    $top[$last][] = $section;
                    continue;
                }
                $last = $section;
            } else {
                $last = null;
            }
            $top[$section] = [];
        }
        /** @var array<string,string[]> */
        $sections = array_merge($top, $sections);

        $groups = [];
        foreach ($sections as $section => $lines) {
            if ($clean) {
                $section = Pcre::replace($regex, '', $section, 1);
            }

            $marked = false;
            if ($marker !== null &&
                    !($markerIsItem && strpos($section, $marker) === 0) &&
                    !Pcre::match($regex, $section)) {
                $section = $marker . $section;
                $marked = true;
            }

            if (!$lines) {
                $groups[] = $section;
                continue;
            }

            // Don't separate or indent top-level list items collected above
            if (!$marked && Pcre::match($regex, $section)) {
                $groups[] = implode("\n", [$section, ...$lines]);
                continue;
            }

            $groups[] = $section;
            $groups[] = $indent . implode("\n" . $indent, $lines);
        }

        return implode($separator, $groups);
    }
}
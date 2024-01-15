<?php declare(strict_types=1);

namespace Lkrms\Console;

use Lkrms\Console\Catalog\ConsoleLevel as Level;
use Lkrms\Console\Catalog\ConsoleMessageType as MessageType;
use Lkrms\Console\Catalog\ConsoleTag as Tag;
use Lkrms\Console\Contract\ConsoleFormatInterface as Format;
use Lkrms\Console\Contract\ConsoleTargetInterface as Target;
use Lkrms\Console\Support\ConsoleMessageAttributes as MessageAttributes;
use Lkrms\Console\Support\ConsoleMessageFormat as MessageFormat;
use Lkrms\Console\Support\ConsoleMessageFormats as MessageFormats;
use Lkrms\Console\Support\ConsoleTagAttributes as TagAttributes;
use Lkrms\Console\Support\ConsoleTagFormats as TagFormats;
use Lkrms\Exception\UnexpectedValueException;
use Lkrms\Support\Catalog\RegularExpression as Regex;
use Lkrms\Utility\Convert;
use Lkrms\Utility\Pcre;
use Lkrms\Utility\Str;
use LogicException;

/**
 * Formats messages for a console output target
 *
 * @see Target::getFormatter()
 */
final class ConsoleFormatter
{
    public const DEFAULT_LEVEL_PREFIX_MAP = [
        Level::EMERGENCY => ' !! ',
        Level::ALERT => ' !! ',
        Level::CRITICAL => ' !! ',
        Level::ERROR => ' !! ',
        Level::WARNING => '  ! ',
        Level::NOTICE => '==> ',
        Level::INFO => ' -> ',
        Level::DEBUG => '--- ',
    ];

    public const DEFAULT_TYPE_PREFIX_MAP = [
        MessageType::GROUP_START => '>>> ',
        MessageType::GROUP_END => '<<< ',
        MessageType::SUCCESS => ' // ',
    ];

    /**
     * Splits the subject into formattable paragraphs, fenced code blocks and
     * code spans
     */
    private const PARSER_REGEX = <<<'REGEX'
        (?msx)
        (?(DEFINE)
          (?<endofline> \h*+ \n )
          (?<endofblock> ^ \k<indent> \k<fence> \h*+ $ )
          (?<endofspan> \k<backtickstring> (?! ` ) )
        )
        # Do not allow gaps between matches
        \G
        # Do not allow empty matches
        (?= . )
        # Claim indentation early so horizontal whitespace before fenced code
        # blocks is not mistaken for text
        (?<indent> ^ \h*+ )?
        (?:
          # Whitespace before paragraphs
          (?<breaks> (?&endofline)+ ) |
          # Everything except unescaped backticks until the start of the next
          # paragraph
          (?<text> (?> (?: [^\\`\n]+ | \\ [-\\!"\#$%&'()*+,./:;<=>?@[\]^_`{|}~\n] | \\ | \n (?! (?&endofline) ) )+ (?&endofline)* ) ) |
          # CommonMark-compliant fenced code blocks
          (?> (?(indent)
            (?> (?<fence> ```+ ) (?<infostring> [^\n]* ) \n )
            # Match empty blocks--with no trailing newline--and blocks with an
            # empty line by making the subsequent newline conditional on inblock
            (?<block> (?> (?<inblock> (?: (?! (?&endofblock) ) (?: \k<indent> | (?= (?&endofline) ) ) [^\n]* (?: (?= \n (?&endofblock) ) | \n | \z ) )+ )? ) )
            # Allow code fences to terminate at the end of the subject
            (?: (?(inblock) \n ) (?&endofblock) | \z )
          ) ) |
          # CommonMark-compliant code spans
          (?<backtickstring> (?> `+ ) ) (?<span> (?> (?: [^`]+ | (?! (?&endofspan) ) `+ )* ) ) (?&endofspan) |
          # Unmatched backticks
          (?<extra> `+ ) |
          \z
        )
        REGEX;

    /**
     * Matches inline formatting tags used outside fenced code blocks and code
     * spans
     */
    private const TAG_REGEX = <<<'REGEX'
        (?xm)
        (?(DEFINE)
          (?<esc> \\ [-\\!"\#$%&'()*+,./:;<=>?@[\]^_`{|}~] | \\ )
        )
        (?<! \\ ) (?: \\\\ )* \K (?|
          \b  (?<tag> _ {1,3}+ )  (?! \s ) (?> (?<text> (?: [^_\\]+ |    (?&esc) | (?! (?<! \s ) \k<tag> \b ) _ + )* ) ) (?<! \s ) \k<tag> \b |
              (?<tag> \* {1,3}+ ) (?! \s ) (?> (?<text> (?: [^*\\]+ |    (?&esc) | (?! (?<! \s ) \k<tag> ) \* + )* ) )   (?<! \s ) \k<tag>    |
              (?<tag> < )         (?! \s ) (?> (?<text> (?: [^>\\]+ |    (?&esc) | (?! (?<! \s ) > ) > + )* ) )          (?<! \s ) >          |
              (?<tag> ~~ )        (?! \s ) (?> (?<text> (?: [^~\\]+ |    (?&esc) | (?! (?<! \s ) ~~ ) ~ + )* ) )         (?<! \s ) ~~         |
          ^   (?<tag> \#\# ) \h+           (?> (?<text> (?: [^\#\s\\]+ | (?&esc) | \#+ (?! \h* $ ) | \h++ (?! (?: \#+ \h* )? $ ) )* ) ) (?: \h+ \#+ | \h* ) $
        )
        REGEX;

    /**
     * A CommonMark-compliant backslash escape, or an escaped line break with an
     * optional leading space
     */
    private const UNESCAPE_REGEX = <<<'REGEX'
        (?x)
        (?|
          \\ ( [-\\ !"\#$%&'()*+,./:;<=>?@[\]^_`{|}~] ) |
          # Lookbehind assertions are unnecessary because the first branch
          # matches escaped spaces and backslashes
          \  ? \\ ( \n )
        )
        REGEX;

    private static ConsoleFormatter $DefaultFormatter;

    private static TagFormats $DefaultTagFormats;

    private static MessageFormats $DefaultMessageFormats;

    private TagFormats $TagFormats;

    private MessageFormats $MessageFormats;

    /**
     * @var callable(): (int|null)
     */
    private $WidthCallback;

    /**
     * @var array<Level::*,string>
     */
    private array $LevelPrefixMap;

    /**
     * @var array<MessageType::*,string>
     */
    private array $TypePrefixMap;

    /**
     * @param (callable(): (int|null))|null $widthCallback
     * @param array<Level::*,string> $levelPrefixMap
     * @param array<MessageType::*,string> $typePrefixMap
     */
    public function __construct(
        ?TagFormats $tagFormats = null,
        ?MessageFormats $messageFormats = null,
        ?callable $widthCallback = null,
        array $levelPrefixMap = ConsoleFormatter::DEFAULT_LEVEL_PREFIX_MAP,
        array $typePrefixMap = ConsoleFormatter::DEFAULT_TYPE_PREFIX_MAP
    ) {
        $this->TagFormats = $tagFormats ?: $this->getDefaultTagFormats();
        $this->MessageFormats = $messageFormats ?: $this->getDefaultMessageFormats();
        $this->WidthCallback = $widthCallback ?: fn(): ?int => null;
        $this->LevelPrefixMap = $levelPrefixMap;
        $this->TypePrefixMap = $typePrefixMap;
    }

    /**
     * Get the format assigned to a tag
     *
     * @param Tag::* $tag
     */
    public function getTagFormat($tag): Format
    {
        return $this->TagFormats->get($tag);
    }

    /**
     * Get the format assigned to a message level and type
     *
     * @param Level::* $level
     * @param MessageType::* $type
     */
    public function getMessageFormat($level, $type = MessageType::STANDARD): MessageFormat
    {
        return $this->MessageFormats->get($level, $type);
    }

    /**
     * Get the prefix assigned to a message level and type
     *
     * @param Level::* $level
     * @param MessageType::* $type
     */
    public function getMessagePrefix($level, $type = MessageType::STANDARD): string
    {
        return
            $type === MessageType::UNFORMATTED || $type === MessageType::UNDECORATED
                ? ''
                : ($this->TypePrefixMap[$type]
                    ?? $this->LevelPrefixMap[$level]
                    ?? '');
    }

    /**
     * Format a string
     *
     * Applies target-defined formats to text that may contain Markdown-like
     * inline formatting tags. Paragraphs outside preformatted blocks are
     * optionally wrapped to a given width, and backslash-escaped punctuation
     * characters and line breaks are preserved.
     *
     * Escaped line breaks may have a leading space, so the following are
     * equivalent:
     *
     * ```
     * Text with a \
     * hard line break.
     *
     * Text with a\
     * hard line break.
     * ```
     *
     * @param array{int,int}|int|null $wrapToWidth If `null` (the default), text
     * is not wrapped.
     *
     * If `$wrapToWidth` is an `array`, the first line of text is wrapped to the
     * first value, and text in subsequent lines is wrapped to the second value.
     *
     * Widths less than or equal to `0` are added to the width reported by the
     * target, and text is wrapped to the result.
     */
    public function formatTags(
        string $string,
        bool $unwrap = false,
        $wrapToWidth = null,
        bool $unescape = true
    ): string {
        if ($string === '' || $string === "\r") {
            return $string;
        }

        /**
         * [ [ Offset, length, replacement ] ]
         *
         * @var array<array{int,int,string}>
         */
        $replace = [];
        $append = '';
        $plainFormats = $this->getDefaultTagFormats();

        // Preserve trailing carriage returns
        if ($string[-1] === "\r") {
            $append .= "\r";
            $string = substr($string, 0, -1);
        }

        // Normalise line endings and split the string into formattable text,
        // fenced code blocks and code spans
        if (!Pcre::matchAll(
            Regex::delimit(self::PARSER_REGEX) . 'u',
            Str::setEol($string),
            $matches,
            \PREG_SET_ORDER | \PREG_UNMATCHED_AS_NULL
        )) {
            throw new UnexpectedValueException(
                sprintf('Unable to parse: %s', $string)
            );
        }

        $string = '';
        /** @var array<int|string,string|null> $match */
        foreach ($matches as $match) {
            $indent = (string) $match['indent'];

            if ($match['breaks'] !== null) {
                $string .= $indent . $match['breaks'];
                continue;
            }

            // Treat unmatched backticks as plain text
            if ($match['extra'] !== null) {
                $string .= $indent . $match['extra'];
                continue;
            }

            $baseOffset = strlen($string . $indent);

            if ($match['text'] !== null) {
                $text = $match['text'];
                if ($unwrap && strpos($text, "\n") !== false) {
                    $text = Convert::unwrap($text, "\n", false, true, true);
                }

                $adjust = 0;
                $text = Pcre::replaceCallback(
                    Regex::delimit(self::TAG_REGEX) . 'u',
                    function (array $match) use (
                        $unescape,
                        &$replace,
                        $plainFormats,
                        $baseOffset,
                        &$adjust
                    ): string {
                        /** @var array<int|string,array{string,int}> $match */
                        $text = $this->applyTags($match, true, true, $plainFormats);
                        $placeholder = Pcre::replace('/[^ ]/u', 'x', $text);
                        $formatted =
                            $unescape && $plainFormats === $this->TagFormats
                                ? $text
                                : $this->applyTags($match, true, $unescape, $this->TagFormats);
                        $replace[] = [
                            $baseOffset + $match[0][1] + $adjust,
                            strlen($placeholder),
                            $formatted,
                        ];
                        $adjust += strlen($placeholder) - strlen($match[0][0]);
                        return $placeholder;
                    },
                    $text,
                    -1,
                    $count,
                    \PREG_OFFSET_CAPTURE
                );

                $string .= $indent . $text;
                continue;
            }

            if ($match['block'] !== null) {
                // Reinstate unwrapped newlines before blocks
                if ($unwrap && $string !== '' && $string[-1] !== "\n") {
                    $string[-1] = "\n";
                }

                $formatted = $this->TagFormats->apply(
                    $match['block'],
                    new TagAttributes(
                        Tag::CODE_BLOCK,
                        $match['fence'],
                        $indent,
                        Str::coalesce(trim($match['infostring']), null),
                    )
                );
                $placeholder = '?';
                $replace[] = [
                    $baseOffset,
                    1,
                    $formatted,
                ];

                $string .= $indent . $placeholder;
                continue;
            }

            if ($match['span'] !== null) {
                $span = $match['span'];
                // As per CommonMark:
                // - Convert line endings to spaces
                // - If the string begins and ends with a space but doesn't
                //   consist entirely of spaces, remove both
                $span = Pcre::replace(
                    '/^ ((?> *[^ ]+).*) $/u',
                    '$1',
                    strtr($span, "\n", ' '),
                );
                $formatted = $this->TagFormats->apply(
                    $span,
                    new TagAttributes(
                        Tag::CODE_SPAN,
                        $match['backtickstring'],
                    )
                );
                $placeholder = Pcre::replace('/[^ ]/u', 'x', $span);
                $replace[] = [
                    $baseOffset,
                    strlen($placeholder),
                    $formatted,
                ];

                $string .= $indent . $placeholder;
                continue;
            }
        }

        // Remove backslash escapes and adjust the offsets of any subsequent
        // replacement strings
        $adjustable = [];
        foreach ($replace as $i => [$offset]) {
            $adjustable[$i] = $offset;
        }
        $adjust = 0;
        $placeholders = 0;
        $string = Pcre::replaceCallback(
            Regex::delimit(self::UNESCAPE_REGEX) . 'u',
            function (array $match) use ($unescape, &$replace, &$adjustable, &$adjust, &$placeholders): string {
                /** @var array<int|string,array{string,int}> $match */
                $delta = strlen($match[1][0]) - strlen($match[0][0]);
                foreach ($adjustable as $i => $offset) {
                    if ($offset < $match[0][1]) {
                        continue;
                    }
                    $replace[$i][0] += $delta;
                }

                $placeholder = null;
                if ($match[1][0] === ' ') {
                    $placeholder = 'x';
                    $placeholders++;
                }

                if (!$unescape || $placeholder) {
                    // Use `$replace` to reinstate the escape after wrapping
                    $replace[] = [
                        $match[0][1] + $adjust,
                        strlen($match[1][0]),
                        !$unescape ? $match[0][0] : $match[1][0],
                    ];
                }

                $adjust += $delta;

                return $placeholder ?? $match[1][0];
            },
            $string,
            -1,
            $count,
            \PREG_OFFSET_CAPTURE
        );

        if (is_array($wrapToWidth)) {
            for ($i = 0; $i < 2; $i++) {
                if ($wrapToWidth[$i] <= 0) {
                    $width = $width ?? ($this->WidthCallback)();
                    if ($width === null) {
                        $wrapToWidth = null;
                        break;
                    }
                    $wrapToWidth[$i] = max(0, $wrapToWidth[$i] + $width);
                }
            }
        } elseif (is_int($wrapToWidth) &&
                $wrapToWidth <= 0) {
            $width = ($this->WidthCallback)();
            $wrapToWidth =
                $width === null
                    ? null
                    : max(0, $wrapToWidth + $width);
        }
        if ($wrapToWidth !== null) {
            $string = Str::wordwrap($string, $wrapToWidth);
        }

        // If `$unescape` is false, entries in `$replace` may be out of order
        if (!$unescape || $placeholders) {
            usort($replace, fn(array $a, array $b): int => $a[0] <=> $b[0]);
        }

        $replace = array_reverse($replace);
        foreach ($replace as [$offset, $length, $replacement]) {
            $string = substr_replace($string, $replacement, $offset, $length);
        }

        if (\PHP_EOL !== "\n") {
            $string = str_replace("\n", \PHP_EOL, $string);
        }

        return $string . $append;
    }

    /**
     * Format a message
     *
     * @param Level::* $level
     * @param MessageType::* $type
     */
    public function formatMessage(
        string $msg1,
        ?string $msg2 = null,
        $level = Level::INFO,
        $type = MessageType::STANDARD
    ): string {
        $attributes = new MessageAttributes($level, $type);

        if ($type === MessageType::UNFORMATTED) {
            return $this
                ->getDefaultMessageFormats()
                ->get($level, $type)
                ->apply($msg1, $msg2, '', $attributes);
        }

        $prefix = $this->getMessagePrefix($level, $type);

        return $this
            ->MessageFormats
            ->get($level, $type)
            ->apply($msg1, $msg2, $prefix, $attributes);
    }

    /**
     * Format a unified diff
     */
    public function formatDiff(string $diff): string
    {
        $formats = [
            '+' => $this->TagFormats->get(Tag::DIFF_ADDITION),
            '-' => $this->TagFormats->get(Tag::DIFF_REMOVAL),
            '@' => $this->TagFormats->get(Tag::DIFF_HEADER),
        ];

        return Pcre::replaceCallback(
            '/^([+\-@]).*/m',
            fn(array $matches) => $formats[$matches[1]]->apply($matches[0]),
            $diff,
        );
    }

    /**
     * Escape special characters, optionally including newlines, in a string
     */
    public static function escapeTags(string $string, bool $newlines = false): string
    {
        // Only escape recognised tag delimiters to minimise the risk of
        // PREG_JIT_STACKLIMIT_ERROR
        $escaped = addcslashes($string, '\#*<>_`~');
        return $newlines
            ? str_replace("\n", "\\\n", $escaped)
            : $escaped;
    }

    /**
     * Remove inline formatting tags from a string
     */
    public static function removeTags(string $string): string
    {
        return self::getDefaultFormatter()->formatTags($string);
    }

    private static function getDefaultFormatter(): self
    {
        return self::$DefaultFormatter ??= new self();
    }

    private static function getDefaultTagFormats(): TagFormats
    {
        return self::$DefaultTagFormats ??= new TagFormats();
    }

    private static function getDefaultMessageFormats(): MessageFormats
    {
        return self::$DefaultMessageFormats ??= new MessageFormats();
    }

    /**
     * @param array<int|string,array{string,int}|string> $match
     */
    private function applyTags(array $match, bool $matchHasOffset, bool $unescape, TagFormats $formats): string
    {
        /** @var string */
        $text = $matchHasOffset ? $match['text'][0] : $match['text'];
        $text = Pcre::replaceCallback(
            Regex::delimit(self::TAG_REGEX) . 'u',
            fn(array $match): string =>
                $this->applyTags($match, false, $unescape, $formats),
            $text
        );

        if ($unescape) {
            $text = Pcre::replace(
                Regex::delimit(self::UNESCAPE_REGEX) . 'u', '$1', $text
            );
        }

        /** @var string */
        $tag = $matchHasOffset ? $match['tag'][0] : $match['tag'];
        switch ($tag) {
            case '___':
            case '***':
            case '##':
                return $formats->apply($text, new TagAttributes(Tag::HEADING, $tag));

            case '__':
            case '**':
                return $formats->apply($text, new TagAttributes(Tag::BOLD, $tag));

            case '_':
            case '*':
                return $formats->apply($text, new TagAttributes(Tag::ITALIC, $tag));

            case '<':
                return $formats->apply($text, new TagAttributes(Tag::UNDERLINE, $tag));

            case '~~':
                return $formats->apply($text, new TagAttributes(Tag::LOW_PRIORITY, $tag));
        }

        throw new LogicException(sprintf('Invalid tag: %s', $tag));
    }
}

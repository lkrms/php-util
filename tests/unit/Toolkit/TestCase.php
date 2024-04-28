<?php declare(strict_types=1);

namespace Salient\Tests;

use Salient\Contract\Core\MessageLevel as Level;
use Salient\Core\Utility\Pcre;
use Closure;
use Throwable;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected const PHP_COMMAND = [\PHP_BINARY, '-ddisplay_startup_errors=0'];

    /**
     * Fail if a callback does not throw a given exception
     *
     * @param Closure(): mixed $callback
     * @param class-string<Throwable> $exception
     */
    public static function assertCallbackThrowsException(
        Closure $callback,
        string $exception,
        ?string $exceptionMessage = null,
        string $message = ''
    ): void {
        try {
            $callback();
        } catch (Throwable $ex) {
            static::assertInstanceOf($exception, $ex, $message);
            if ($exceptionMessage !== null) {
                static::assertStringContainsString(
                    $exceptionMessage,
                    $ex->getMessage(),
                    $message
                );
            }
            return;
        }
        static::fail($message === ''
            ? sprintf('Failed asserting that exception of type %s is thrown', $exception)
            : $message);
    }

    /**
     * Assert that the given console messages are written, converting line
     * endings if necessary
     *
     * @param array<array{Level::*,string,2?:array<string,mixed>}> $expected
     * @param array<array{Level::*,string,2?:array<string,mixed>}> $actual
     */
    public static function assertSameConsoleMessages(
        array $expected,
        array $actual,
        string $message = ''
    ): void {
        foreach ($expected as $i => &$expectedMessage) {
            $expectedMessage[1] = static::normaliseConsoleOutput($expectedMessage[1]);
            if (!isset($expectedMessage[2]) && isset($actual[$i][2])) {
                unset($actual[$i][2]);
            }
        }
        static::assertEquals($expected, $actual, $message);
    }

    /**
     * Expect an exception if a given value is a string
     *
     * If `$expected` is a string with no commas, it is passed to
     * {@see expectException()}. If it is a string with at least one comma, text
     * before the first comma is passed to {@see expectException()}, and text
     * after the comma is passed to {@see expectExceptionMessage()}.
     *
     * If `$expected` is not a string, no action is taken.
     *
     * @param string|mixed $expected
     */
    public function maybeExpectException($expected): void
    {
        if (!is_string($expected)) {
            return;
        }
        $split = explode(',', $expected, 2);
        if (count($split) === 2) {
            $expected = $split[0];
            $this->expectExceptionMessage($split[1]);
        }
        /** @var class-string<Throwable> $expected */
        $this->expectException($expected);
    }

    /**
     * Get the path to the fixtures directory for a class
     */
    public static function getFixturesPath(string $class): string
    {
        return dirname(__DIR__, 2)
            . '/fixtures/'
            . Pcre::replace([
                '/^Salient\\\\(?|Tests\\\\(.+)Test$|(.+))/',
                '/\\\\/',
            ], [
                'Toolkit/$1',
                '/',
            ], $class);
    }

    /**
     * Get the path to the root directory of the package
     */
    public static function getPackagePath(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * Replace directory separators in a string with DIRECTORY_SEPARATOR
     *
     * @template T of string|null
     *
     * @param T $string
     * @return T
     */
    public static function directorySeparatorToNative(?string $string): ?string
    {
        /** @var T */
        return $string === null
            ? null
            : str_replace('/', \DIRECTORY_SEPARATOR, $string);
    }

    /**
     * Normalise line endings in console output
     *
     * @template T of string|null
     *
     * @param T $output
     * @return T
     */
    public static function normaliseConsoleOutput(?string $output): ?string
    {
        /** @var T */
        return $output === null
            ? null
            : str_replace(
                ["\r" . \PHP_EOL, \PHP_EOL],
                ["\r", "\n"],
                $output,
            );
    }
}

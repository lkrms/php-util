<?php declare(strict_types=1);

namespace Salient\Tests\Core\Utility;

use Salient\Core\Utility\File;
use Salient\Core\Utility\Sys;
use Salient\Tests\TestCase;

/**
 * @covers \Salient\Core\Utility\Sys
 */
final class SysTest extends TestCase
{
    /**
     * @dataProvider escapeCommandProvider
     */
    public function testEscapeCommand(string $arg): void
    {
        if (Sys::isWindows() && strpos($arg, \PHP_EOL) !== false) {
            $this->markTestSkipped();
        }

        $command = [
            \PHP_BINARY,
            '-ddisplay_startup_errors=0',
            $this->getFixturesPath(__CLASS__) . '/unescape.php',
            $arg,
        ];
        $command = Sys::escapeCommand($command);
        $handle = File::openPipe($command, 'rb');
        $output = File::getContents($handle);
        $status = File::closePipe($handle);
        $this->assertSame(0, $status);
        $this->assertSame($arg . \PHP_EOL, $output);
    }

    /**
     * @return array<string,array{string}>
     */
    public static function escapeCommandProvider(): array
    {
        return [
            'empty string' => [
                '',
            ],
            'special characters' => [
                '!"$%&\'*+,;<=>?[\]^`{|}~',
            ],
            'special characters + whitespace' => [
                ' ! " $ % & \' * + , ; < = > ? [ \ ] ^ ` { | } ~ ',
            ],
            'path' => [
                '/some/path',
            ],
            'path + spaces' => [
                '/some/path with spaces',
            ],
            'quoted' => [
                '"string"',
            ],
            'quoted + backslashes' => [
                '"\string\"',
            ],
            'quoted + whitespace' => [
                '"string with words"',
            ],
            'quoted + whitespace + backslashes' => [
                '"\string with words\"',
            ],
            'quoted (single + double)' => [
                '\'quotable\' "quotes"',
            ],
            'unquoted + special (cmd) #1' => [
                'this&that',
            ],
            'unquoted + special (cmd) #2' => [
                'this^that',
            ],
            'unquoted + special (cmd) #3' => [
                '(this|that)',
            ],
            'cmd variable expansion #1' => [
                '%path%',
            ],
            'cmd variable expansion #2' => [
                '!path!',
            ],
            'cmd variable expansion #3' => [
                'value%',
            ],
            'cmd variable expansion #4' => [
                'success!',
            ],
            'with newline' => [
                'line' . \PHP_EOL . 'line',
            ],
            'with blank line' => [
                'line' . \PHP_EOL . \PHP_EOL . 'line',
            ],
            'with trailing newline' => [
                'line' . \PHP_EOL,
            ],
            'with trailing space' => [
                'string ',
            ],
        ];
    }

    public function testIsWindows(): void
    {
        $this->assertSame(\DIRECTORY_SEPARATOR === '\\', Sys::isWindows());
    }
}

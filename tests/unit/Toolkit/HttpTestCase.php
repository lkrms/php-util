<?php declare(strict_types=1);

namespace Salient\Tests;

use Salient\Contract\Catalog\FileDescriptor;
use Salient\Contract\Http\HttpHeader as Header;
use Salient\Contract\Http\HttpResponseInterface;
use Salient\Core\Process;
use Salient\Curler\Curler;
use Salient\Http\HttpHeaders;
use Salient\Utility\File;
use Salient\Utility\Str;
use RuntimeException;

abstract class HttpTestCase extends TestCase
{
    protected const HTTP_SERVER_HOST = 'localhost';
    protected const HTTP_SERVER_PORT = '3007';
    protected const HTTP_SERVER_AUTHORITY = self::HTTP_SERVER_HOST . ':' . self::HTTP_SERVER_PORT;
    protected const HTTP_SERVER_URI = 'http://' . self::HTTP_SERVER_AUTHORITY;

    protected const HTTP_HEADER_IGNORE_LIST = [
        Header::ACCEPT_ENCODING,
        Header::CONNECTION,
        Header::USER_AGENT,
    ];

    private string $ResponseDir;
    private Process $HttpServer;

    /**
     * Assert that the given lists of HTTP messages are the same after
     * normalising line endings, removing ignored headers and sorting the
     * remaining headers
     *
     * @param string[] $expected
     * @param string[] $actual
     * @param string[] $ignore
     */
    public static function assertSameHttpMessages(
        array $expected,
        array $actual,
        array $ignore = self::HTTP_HEADER_IGNORE_LIST,
        string $message = ''
    ): void {
        self::assertSameSize($expected, $actual, $message);
        [$_expected, $_actual] = [$expected, $actual];
        foreach ($_expected as $expected) {
            /** @var string */
            $actual = array_shift($_actual);
            self::assertSameHttpMessage($expected, $actual, $ignore, $message);
        }
    }

    /**
     * Assert that the given HTTP messages are the same after normalising line
     * endings, removing ignored headers and sorting the remaining headers
     *
     * @param string[] $ignore
     */
    public static function assertSameHttpMessage(
        string $expected,
        string $actual,
        array $ignore = self::HTTP_HEADER_IGNORE_LIST,
        string $message = ''
    ): void {
        $expected = str_replace(
            ['{{authority}}', '{{uri}}'],
            [self::HTTP_SERVER_AUTHORITY, self::HTTP_SERVER_URI],
            Str::setEol($expected, "\r\n"),
        );

        $actual = explode("\r\n\r\n", $actual, 2);
        $headers = HttpHeaders::from($actual[0])->except($ignore)->sort();
        $actual[0] = implode("\r\n", [
            explode("\r\n", $actual[0], 2)[0],
            ...$headers->getLines(),
        ]);
        $actual = implode("\r\n\r\n", $actual);

        self::assertSame($expected, $actual, $message);
    }

    /**
     * Get a Curler instance bound to the server started by startHttpServer()
     */
    protected static function getCurler(string $endpoint = ''): Curler
    {
        return new Curler(self::HTTP_SERVER_URI . $endpoint);
    }

    /**
     * @param HttpResponseInterface|string ...$responses
     */
    protected function startHttpServer(...$responses): Process
    {
        $this->stopHttpServer();

        if ($responses === [] || count($responses) > 1) {
            $args[] = '--';
            if ($responses !== []) {
                $dir = File::createTempDir();
                foreach ($responses as $i => $response) {
                    $file = sprintf('%s/%d.http', $dir, $i);
                    File::writeContents($file, $this->filterResponse($response));
                    $args[] = $file;
                }
                $this->ResponseDir = $dir;
            }
        } else {
            $input = $this->filterResponse($responses[0]);
        }

        $process = new Process([
            ...self::PHP_COMMAND,
            __DIR__ . '/http-server.php',
            self::HTTP_SERVER_AUTHORITY,
            '300',
            ...($args ?? []),
        ], $input ?? '');

        $process->start();

        while ($process->poll()->isRunning()) {
            if (strpos(
                $process->getOutput(FileDescriptor::ERR),
                'Server started at ' . self::HTTP_SERVER_URI,
            ) !== false) {
                return $this->HttpServer = $process;
            }
        }

        throw new RuntimeException(sprintf(
            "Error starting HTTP server (status: %d; stdout: '%s'; stderr: '%s')",
            $process->getExitStatus(),
            $process->getOutput(),
            $process->getOutput(FileDescriptor::ERR),
        ));
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        $this->stopHttpServer();
    }

    private function stopHttpServer(): void
    {
        if (isset($this->HttpServer)) {
            $this->HttpServer->stop();
            unset($this->HttpServer);
        }

        if (isset($this->ResponseDir)) {
            if (is_dir($this->ResponseDir)) {
                File::pruneDir($this->ResponseDir, true);
            }
            unset($this->ResponseDir);
        }
    }

    /**
     * @param HttpResponseInterface|string $response
     */
    private function filterResponse($response): string
    {
        if (
            $response instanceof HttpResponseInterface
            && !$response->hasHeader(Header::CONTENT_LENGTH)
            && ($size = $response->getBody()->getSize()) !== null
        ) {
            $response = $response->withHeader(Header::CONTENT_LENGTH, (string) $size);
        }

        return (string) $response;
    }
}

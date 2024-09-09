<?php declare(strict_types=1);

namespace Salient\Tests\Curler;

use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\RequestInterface;
use Salient\Cache\CacheStore;
use Salient\Contract\Core\MimeType;
use Salient\Contract\Curler\Event\CurlResponseEventInterface;
use Salient\Contract\Curler\Exception\CurlErrorExceptionInterface;
use Salient\Contract\Curler\Exception\HttpErrorExceptionInterface;
use Salient\Contract\Curler\Exception\TooManyRedirectsExceptionInterface;
use Salient\Contract\Curler\CurlerInterface;
use Salient\Contract\Curler\CurlerPageInterface;
use Salient\Contract\Curler\CurlerPagerInterface;
use Salient\Contract\Http\HttpHeader as Header;
use Salient\Contract\Http\HttpRequestMethod as Method;
use Salient\Contract\Http\HttpResponseInterface;
use Salient\Core\Facade\Event;
use Salient\Core\Process;
use Salient\Curler\Curler;
use Salient\Curler\CurlerFile;
use Salient\Curler\CurlerPage;
use Salient\Http\Http;
use Salient\Http\HttpHeaders;
use Salient\Http\HttpResponse;
use Salient\Http\HttpStream;
use Salient\Tests\HttpTestCase;
use Salient\Utility\Arr;
use Salient\Utility\File;
use Salient\Utility\Json;
use Salient\Utility\Str;

/**
 * @covers \Salient\Curler\Curler
 * @covers \Salient\Curler\CurlerFile
 * @covers \Salient\Curler\CurlerPage
 */
final class CurlerTest extends HttpTestCase
{
    private const QUERY = ['quux' => 1];
    private const IN = ['baz' => 'qux'];
    private const OUT = ['foo' => 'bar'];
    private const OUT_PAGES = [[['name' => 'foo'], ['name' => 'bar'], ['name' => 'baz']], [['name' => 'qux']]];

    private int $ListenerId;

    public function testGet(): void
    {
        $server = $this->getJsonServer(self::OUT);
        $this->assertSame(self::OUT, $this->getCurler('/foo')->get(self::QUERY));
        $this->assertSameHttpMessage(
            <<<EOF
GET /foo?quux=1 HTTP/1.1
Host: {{authority}}
Accept: application/json


EOF,
            $server->getOutput(),
        );
    }

    public function testHead(): void
    {
        $server = $this->getJsonServer(self::OUT);
        $this->assertSame([
            'content-type' => [MimeType::JSON],
            'content-length' => ['13'],
        ], $this->getCurler('/foo')->head(self::QUERY)->all());
        $this->assertSameHttpMessage(
            <<<EOF
HEAD /foo?quux=1 HTTP/1.1
Host: {{authority}}
Accept: application/json


EOF,
            $server->getOutput(),
        );
    }

    public function testPost(): void
    {
        $this->doTestPost();
    }

    public function testPut(): void
    {
        $this->doTestPost(Method::PUT);
    }

    public function testPatch(): void
    {
        $this->doTestPost(Method::PATCH);
    }

    public function testDelete(): void
    {
        $this->doTestPost(Method::DELETE);
    }

    private function doTestPost(string $method = Method::POST): void
    {
        $server = $this->getJsonServer(self::OUT, self::OUT, self::OUT);
        $m = Str::lower($method);

        $this->assertSame(self::OUT, $this->getCurler('/foo')->{$m}(self::IN, self::QUERY));
        $this->assertSameHttpMessage(
            <<<EOF
{$method} /foo?quux=1 HTTP/1.1
Host: {{authority}}
Accept: application/json
Content-Length: 13
Content-Type: application/json

{"baz":"qux"}
EOF,
            $server->getOutput(),
        );

        $this->assertSame(self::OUT, $this->getCurler('/foo')->{$m}(null, self::QUERY));
        $this->assertSameHttpMessage(
            <<<EOF
{$method} /foo?quux=1 HTTP/1.1
Host: {{authority}}
Accept: application/json
Content-Length: 0
Content-Type: application/x-www-form-urlencoded


EOF,
            $server->getNewOutput(),
        );

        $file = self::getFixturesPath(__CLASS__) . '/profile.gif';
        $data = self::IN + [
            'attachment' => new CurlerFile($file),
        ];
        $curler = $this->getCurler('/foo');
        $result = $curler->{$m}($data, self::QUERY);
        $output = $server->getNewOutput();
        $boundaryParam = HttpHeaders::from($output)->getMultipartBoundary();
        $this->assertNotNull($boundaryParam);
        $boundary = Http::unquoteString($boundaryParam);
        $content = File::getContents($file);
        $length = 169 + strlen($content) + 3 * strlen($boundary);
        $this->assertSame(self::OUT, $result);
        $this->assertSameHttpMessage(
            <<<EOF
{$method} /foo?quux=1 HTTP/1.1
Host: {{authority}}
Accept: application/json
Content-Length: {$length}
Content-Type: multipart/form-data; boundary={$boundaryParam}

--{$boundary}
Content-Disposition: form-data; name="baz"

qux
--{$boundary}
Content-Disposition: form-data; name="attachment"; filename="profile.gif"
Content-Type: image/gif

{$content}
--{$boundary}--

EOF,
            $output,
        );
    }

    public function testGetP(): void
    {
        $this->doTestGetP();
    }

    public function testPostP(): void
    {
        $this->doTestGetP(Method::POST);
    }

    public function testPutP(): void
    {
        $this->doTestGetP(Method::PUT);
    }

    public function testPatchP(): void
    {
        $this->doTestGetP(Method::PATCH);
    }

    public function testDeleteP(): void
    {
        $this->doTestGetP(Method::DELETE);
    }

    private function doTestGetP(string $method = Method::GET): void
    {
        $server = $this->getJsonServer(...self::OUT_PAGES);
        $output = [];
        $m = Str::lower($method) . 'P';

        /** @var CurlerPagerInterface&MockObject */
        $pager = $this->createMock(CurlerPagerInterface::class);
        $pager
            ->expects($this->once())
            ->method('getFirstRequest')
            ->willReturnArgument(0);
        $pager
            ->expects($this->exactly(2))
            ->method('getPage')
            ->willReturnCallback(
                function (
                    $data,
                    RequestInterface $request,
                    HttpResponseInterface $response,
                    CurlerInterface $curler,
                    ?CurlerPageInterface $previousPage = null,
                    ?array $query = null
                ) use ($server, &$output): CurlerPage {
                    $output[] = $server->getNewOutput();
                    return new CurlerPage(
                        $data,
                        $previousPage
                            ? null
                            : $request
                                ->withMethod(Method::GET)
                                ->withUri($curler->replaceQuery($request->getUri(), ['page' => 2]))
                                ->withBody(HttpStream::fromString(''))
                                ->withoutHeader(Header::CONTENT_TYPE)
                    );
                }
            );

        $curler = $this->getCurler('/foo')->withPager($pager);
        $args = $method === Method::GET ? [] : [self::IN];
        $body = $method === Method::GET ? '' : '{"baz":"qux"}';
        $headers = $method === Method::GET ? '' : <<<EOF

Content-Length: 13
Content-Type: application/json
EOF;
        $result = iterator_to_array($curler->{$m}(...$args));
        $this->assertSame(Arr::flatten(self::OUT_PAGES, 1), $result);
        $this->assertSameHttpMessages([
            <<<EOF
{$method} /foo HTTP/1.1
Host: {{authority}}
Accept: application/json{$headers}

{$body}
EOF,
            <<<EOF
GET /foo?page=2 HTTP/1.1
Host: {{authority}}
Accept: application/json


EOF,
        ], $output);
    }

    public function testPostR(): void
    {
        $this->doTestPostR();
    }

    public function testPutR(): void
    {
        $this->doTestPostR(Method::PUT);
    }

    public function testPatchR(): void
    {
        $this->doTestPostR(Method::PATCH);
    }

    public function testDeleteR(): void
    {
        $this->doTestPostR(Method::DELETE);
    }

    private function doTestPostR(string $method = Method::POST): void
    {
        $server = $this->getJsonServer(self::OUT);
        $m = Str::lower($method) . 'R';

        $file = self::getFixturesPath(__CLASS__) . '/profile.gif';
        $content = File::getContents($file);
        $length = strlen($content);
        $curler = $this->getCurler('/foo')->withExpectJson(false);
        $this->assertSame(self::OUT, $curler->{$m}($content, 'image/gif', self::QUERY));
        $this->assertSameHttpMessage(
            <<<EOF
{$method} /foo?quux=1 HTTP/1.1
Host: {{authority}}
Accept: */*
Content-Length: {$length}
Content-Type: image/gif

{$content}
EOF,
            $server->getOutput(),
        );
    }

    public function testCaching(): void
    {
        $server = $this->getJsonServer(self::OUT, [], self::OUT, self::OUT, [], self::OUT);
        $cache = new CacheStore();

        $curler = $this
            ->getCurler('/foo')
            ->withCacheStore($cache)
            ->withResponseCache();
        $this->assertSame(self::OUT, $curler->get(self::QUERY));
        $this->assertSameHttpMessage(
            $request = <<<EOF
GET /foo?quux=1 HTTP/1.1
Host: {{authority}}
Accept: application/json


EOF,
            $server->getOutput(),
        );
        $this->assertSame(self::OUT, $curler->get(self::QUERY));
        $this->assertSame('', $server->getNewOutput());

        $curler = $curler->withRefreshCache();
        $this->assertSame([], $curler->get(self::QUERY));
        $this->assertSameHttpMessage($request, $server->getNewOutput());
        $curler = $curler->withRefreshCache(false);
        $this->assertSame([], $curler->get(self::QUERY));
        $this->assertSame('', $server->getNewOutput());

        $curler2 = $curler->withRefreshCache()->withCacheLifetime(-1);
        $this->assertSame(self::OUT, $curler2->get(self::QUERY));
        $this->assertSameHttpMessage($request, $server->getNewOutput());
        $curler2 = $curler2->withRefreshCache(false);
        $this->assertSame([], $curler2->get(self::QUERY));
        $this->assertSame('', $server->getNewOutput());

        $this->assertCount(1, $cacheKeys = $cache->getAllKeys());
        $this->assertSame(self::OUT, $curler->post(self::IN, self::QUERY));
        $this->assertSameHttpMessage(
            $request = <<<EOF
POST /foo?quux=1 HTTP/1.1
Host: {{authority}}
Accept: application/json
Content-Length: 13
Content-Type: application/json

{"baz":"qux"}
EOF,
            $server->getNewOutput(),
        );
        $this->assertSame($cacheKeys, $cache->getAllKeys());

        $curler = $curler->withPostResponseCache();
        $this->assertSame([], $curler->post(self::IN, self::QUERY));
        $this->assertSameHttpMessage($request, $server->getNewOutput());
        $this->assertSame([], $curler->post(self::IN, self::QUERY));
        $this->assertSame('', $server->getNewOutput());
        // Different data = miss
        $this->assertSame(self::OUT, $curler->post([], self::QUERY));
        $this->assertSameHttpMessage(
            <<<EOF
POST /foo?quux=1 HTTP/1.1
Host: {{authority}}
Accept: application/json
Content-Length: 2
Content-Type: application/json

[]
EOF,
            $server->getNewOutput(),
        );
        $this->assertSame([
            Curler::class . ':response:GET:http%3A%2F%2Flocalhost%3A3007%2Ffoo%3Fquux%3D1:a4801c4a7640292ec53bad9241ee376d',
            Curler::class . ':response:POST:http%3A%2F%2Flocalhost%3A3007%2Ffoo%3Fquux%3D1:025083437c7862fbace55c0ed8bbf16b',
            Curler::class . ':response:POST:http%3A%2F%2Flocalhost%3A3007%2Ffoo%3Fquux%3D1:ef13041d0a8b2d2f3084b396b3648def',
        ], $cache->getAllKeys());
        $this->assertCount(0, $cache->asOfNow(time() + 3601)->getAllKeys());
    }

    public function testCurlError(): void
    {
        $this->expectException(CurlErrorExceptionInterface::class);
        (new Curler('//localhost'))->get();
    }

    public function testThrowHttpErrors(): void
    {
        $bad = new HttpResponse(502, '502 bad gateway', [Header::CONTENT_TYPE => MimeType::TEXT]);
        $good = new HttpResponse(200, 'foo', [Header::CONTENT_TYPE => MimeType::TEXT]);
        $server = $this->startHttpServer($bad, $good, $bad);
        $output = [];
        $curler = $this
            ->getCurler()
            ->withCacheStore(new CacheStore())
            ->withResponseCache();

        $this->assertCallbackThrowsException(
            fn() => $curler->get(),
            HttpErrorExceptionInterface::class,
            'HTTP error 502 Bad Gateway',
        );
        $this->assertNotNull($curler->getLastRequest());
        $this->assertNotNull($response = $curler->getLastResponse());
        $this->assertSame(502, $response->getStatusCode());
        $output[] = $server->getOutput();

        $this->assertSame('foo', $curler->get());
        $this->assertNotNull($curler->getLastRequest());
        $this->assertNotNull($response = $curler->getLastResponse());
        $this->assertSame(200, $response->getStatusCode());
        $output[] = $server->getNewOutput();

        $this->assertSame('foo', $curler->get());
        $this->assertNotNull($curler->getLastRequest());
        $this->assertNotNull($response = $curler->getLastResponse());
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('', $server->getNewOutput());

        $expected = <<<EOF
GET / HTTP/1.1
Host: {{authority}}
Accept: application/json


EOF;
        $this->assertSameHttpMessages([$expected, $expected], $output);
    }

    public function testWithoutThrowHttpErrors(): void
    {
        $bad = new HttpResponse(502, '502 bad gateway', [Header::CONTENT_TYPE => MimeType::TEXT]);
        $good = new HttpResponse(200, 'foo', [Header::CONTENT_TYPE => MimeType::TEXT]);
        $server = $this->startHttpServer($bad, $good, $bad);
        $output = [];
        $curler = $this
            ->getCurler()
            ->withThrowHttpErrors(false)
            ->withCacheStore(new CacheStore())
            ->withResponseCache();

        $this->assertSame('502 bad gateway', $curler->get());
        $this->assertNotNull($curler->getLastRequest());
        $this->assertNotNull($response = $curler->getLastResponse());
        $this->assertSame(502, $response->getStatusCode());
        $output[] = $server->getOutput();

        $this->assertSame('foo', $curler->get());
        $this->assertNotNull($curler->getLastRequest());
        $this->assertNotNull($response = $curler->getLastResponse());
        $this->assertSame(200, $response->getStatusCode());
        $output[] = $server->getNewOutput();

        $this->assertSame('foo', $curler->get());
        $this->assertNotNull($curler->getLastRequest());
        $this->assertNotNull($response = $curler->getLastResponse());
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('', $server->getNewOutput());

        $expected = <<<EOF
GET / HTTP/1.1
Host: {{authority}}
Accept: application/json


EOF;
        $this->assertSameHttpMessages([$expected, $expected], $output);
    }

    public function testFollowRedirects(): void
    {
        $responses = [
            new HttpResponse(301, '', [Header::LOCATION => '//' . self::HTTP_SERVER_AUTHORITY . '/foo']),
            new HttpResponse(302, '', [Header::LOCATION => '/foo/bar']),
            new HttpResponse(302, '', [Header::LOCATION => '/foo/bar?baz=1']),
            new HttpResponse(200, Json::stringify(self::OUT), [Header::CONTENT_TYPE => MimeType::JSON]),
        ];
        $server = $this->startHttpServer(...$responses);
        $output = [];
        $curler = $this
            ->getCurler()
            ->withFollowRedirects()
            ->withMaxRedirects(3)
            ->withCacheStore(new CacheStore())
            ->withResponseCache();

        $this->ListenerId = Event::getInstance()->listen(
            function (CurlResponseEventInterface $event) use ($server, &$output) {
                $output[] = $server->getNewOutput();
            }
        );

        $this->assertSame(self::OUT, $curler->get(self::QUERY));
        $this->assertSame(self::OUT, $curler->get(self::QUERY));

        // 4 requests are made if every response is cached
        $this->assertSame('', $server->getNewOutput());
        $this->assertSameHttpMessages([
            <<<EOF
GET /?quux=1 HTTP/1.1
Host: {{authority}}
Accept: application/json


EOF,
            <<<EOF
GET /foo HTTP/1.1
Host: {{authority}}
Accept: application/json


EOF,
            <<<EOF
GET /foo/bar HTTP/1.1
Host: {{authority}}
Accept: application/json


EOF,
            <<<EOF
GET /foo/bar?baz=1 HTTP/1.1
Host: {{authority}}
Accept: application/json


EOF,
        ], $output);
    }

    public function testTooManyRedirects(): void
    {
        $responses = [
            new HttpResponse(301, '', [Header::LOCATION => '//' . self::HTTP_SERVER_AUTHORITY . '/foo']),
            new HttpResponse(302, '', [Header::LOCATION => '/foo/bar']),
            new HttpResponse(302, '', [Header::LOCATION => '/']),
        ];
        $server = $this->startHttpServer(...$responses, ...$responses);
        $output = [];
        $curler = $this
            ->getCurler()
            ->withFollowRedirects()
            ->withMaxRedirects(3)
            ->withCacheStore(new CacheStore())
            ->withResponseCache();

        $this->ListenerId = Event::getInstance()->listen(
            function (CurlResponseEventInterface $event) use ($server, &$output) {
                $output[] = $server->getNewOutput();
            }
        );

        $this->assertCallbackThrowsException(
            fn() => $curler->get(),
            TooManyRedirectsExceptionInterface::class,
            'Redirect limit exceeded: 3',
        );

        // 3 requests are made if the "GET /" response is cached
        $this->assertSame('', $server->getNewOutput());
        $this->assertSameHttpMessages([
            <<<EOF
GET / HTTP/1.1
Host: {{authority}}
Accept: application/json


EOF,
            <<<EOF
GET /foo HTTP/1.1
Host: {{authority}}
Accept: application/json


EOF,
            <<<EOF
GET /foo/bar HTTP/1.1
Host: {{authority}}
Accept: application/json


EOF,
        ], $output);
    }

    protected function tearDown(): void
    {
        if (isset($this->ListenerId)) {
            Event::removeListener($this->ListenerId);
        }

        parent::tearDown();
    }

    /**
     * @param mixed[] ...$data
     */
    private function getJsonServer(array ...$data): Process
    {
        foreach ($data as $data) {
            $responses[] = new HttpResponse(
                200,
                Json::stringify($data),
                [Header::CONTENT_TYPE => MimeType::JSON],
            );
        }
        return $this->startHttpServer(...($responses ?? []));
    }
}

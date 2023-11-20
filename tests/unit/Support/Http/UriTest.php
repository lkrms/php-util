<?php declare(strict_types=1);

namespace Lkrms\Tests\Support\Http;

use Lkrms\Exception\InvalidArgumentException;
use Lkrms\Support\Http\Uri;

/**
 * Unit tests for \Lkrms\Support\Http\Uri
 *
 * Some of the following tests were adapted from
 * {@link https://github.com/guzzle/psr7/blob/2.6/tests/UriTest.php guzzlehttp/psr7's `UriTest` class}
 */
final class UriTest extends \Lkrms\Tests\TestCase
{
    /**
     * @dataProvider uriParsingProvider
     */
    public function testUriParsing(
        string $uri,
        string $scheme = '',
        string $authority = '',
        string $userInfo = '',
        string $host = '',
        ?int $port = null,
        string $path = '',
        string $query = '',
        string $fragment = ''
    ): void {
        $in = $uri;
        $uri = new Uri($uri);

        $this->assertSame($scheme, $uri->getScheme());
        $this->assertSame($authority, $uri->getAuthority());
        $this->assertSame($userInfo, $uri->getUserInfo());
        $this->assertSame($host, $uri->getHost());
        $this->assertSame($port, $uri->getPort());
        $this->assertSame($path, $uri->getPath());
        $this->assertSame($query, $uri->getQuery());
        $this->assertSame($fragment, $uri->getFragment());
        $this->assertSame($in, (string) $uri);
    }

    /**
     * @return array<array<string|int|null>>
     */
    public static function uriParsingProvider(): array
    {
        return [
            [
                'https://user:pass@example.com:8080/path/123?q=abc#test',
                'https',
                'user:pass@example.com:8080',
                'user:pass',
                'example.com',
                8080,
                '/path/123',
                'q=abc',
                'test',
            ],
            [
                'urn:path-rootless',
                'urn',
                '',
                '',
                '',
                null,
                'path-rootless',
            ],
            [
                'urn:path:with:colon',
                'urn',
                '',
                '',
                '',
                null,
                'path:with:colon',
            ],
            [
                'urn:/path-absolute',
                'urn',
                '',
                '',
                '',
                null,
                '/path-absolute',
            ],
            [
                'urn:/',
                'urn',
                '',
                '',
                '',
                null,
                '/',
            ],
            [
                'urn:',
                'urn',
            ],
            [
                '/',
                '',
                '',
                '',
                '',
                null,
                '/',
            ],
            [
                'relative/',
                '',
                '',
                '',
                '',
                null,
                'relative/',
            ],
            [
                '0',
                '',
                '',
                '',
                '',
                null,
                '0',
            ],
            [
                '',
            ],
            [
                '//example.org',
                '',
                'example.org',
                '',
                'example.org',
            ],
            [
                '//example.org/',
                '',
                'example.org',
                '',
                'example.org',
                null,
                '/',
            ],
            [
                '//example.org?q#h',
                '',
                'example.org',
                '',
                'example.org',
                null,
                '',
                'q',
                'h',
            ],
            [
                '?q',
                '',
                '',
                '',
                '',
                null,
                '',
                'q',
            ],
            [
                '?q=abc&foo=bar',
                '',
                '',
                '',
                '',
                null,
                '',
                'q=abc&foo=bar',
            ],
            [
                '#fragment',
                '',
                '',
                '',
                '',
                null,
                '',
                '',
                'fragment',
            ],
            [
                './foo/../bar',
                '',
                '',
                '',
                '',
                null,
                './foo/../bar',
            ],
        ];
    }

    /**
     * @dataProvider uriValidationProvider
     */
    public function testUriValidation(string $uri): void
    {
        $this->assertFalse(
            filter_var($uri, FILTER_VALIDATE_URL),
            'filter_var($uri, FILTER_VALIDATE_URL)'
        );
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid URI: $uri");
        new Uri($uri);
    }

    /**
     * @return array<array{string}>
     */
    public static function uriValidationProvider(): array
    {
        return [
            ['//'],
            ['///'],
            ['//example.com:-1'],
            ['http://'],
            ['urn://host:with:colon'],
            [':path:with:colon'],
        ];
    }

    public function testWith(): void
    {
        $uri = (new Uri())
            ->withScheme('https')
            ->withHost('example.com')
            ->withUserInfo('user', 'pass')
            ->withPort(8080)
            ->withPath('/path/123')
            ->withQuery('q=abc')
            ->withFragment('test');

        $this->assertSame('https', $uri->getScheme());
        $this->assertSame('user:pass@example.com:8080', $uri->getAuthority());
        $this->assertSame('user:pass', $uri->getUserInfo());
        $this->assertSame('example.com', $uri->getHost());
        $this->assertSame(8080, $uri->getPort());
        $this->assertSame('/path/123', $uri->getPath());
        $this->assertSame('q=abc', $uri->getQuery());
        $this->assertSame('test', $uri->getFragment());
        $this->assertSame('https://user:pass@example.com:8080/path/123?q=abc#test', (string) $uri);
    }

    public function testWithInvalidPort(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid port: 100000');
        (new Uri())->withHost('example.com')->withPort(100000);
    }

    public function testWithNegativePort(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid port: -1');
        (new Uri())->withHost('example.com')->withPort(-1);
    }

    public function testUriWithNegativePort(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid URI: //example.com:-1');
        new Uri('//example.com:-1');
    }

    public function testFalsyUriParsing(): void
    {
        $uri = new Uri('//0:0@0/0?0#0');

        self::assertSame('', $uri->getScheme());
        self::assertSame('0:0@0', $uri->getAuthority());
        self::assertSame('0:0', $uri->getUserInfo());
        self::assertSame('0', $uri->getHost());
        self::assertNull($uri->getPort());
        self::assertSame('/0', $uri->getPath());
        self::assertSame('0', $uri->getQuery());
        self::assertSame('0', $uri->getFragment());
        self::assertSame('//0:0@0/0?0#0', (string) $uri);
    }

    public function testWithFalsy(): void
    {
        $uri = (new Uri())
            ->withHost('0')
            ->withUserInfo('0', '0')
            ->withPath('/0')
            ->withQuery('0')
            ->withFragment('0');

        self::assertSame('', $uri->getScheme());
        self::assertSame('0:0@0', $uri->getAuthority());
        self::assertSame('0:0', $uri->getUserInfo());
        self::assertSame('0', $uri->getHost());
        self::assertNull($uri->getPort());
        self::assertSame('/0', $uri->getPath());
        self::assertSame('0', $uri->getQuery());
        self::assertSame('0', $uri->getFragment());
        self::assertSame('//0:0@0/0?0#0', (string) $uri);
    }

    /**
     * @dataProvider normalisationProvider
     *
     * @param array<string,string|int|null> $expectedParts
     */
    public function testNormalisation(
        string $expected,
        string $uri,
        array $expectedParts = [],
        bool $strict = true
    ): void {
        $uri = $strict ? new Uri($uri) : Uri::from($uri);
        $this->assertSame($expected, (string) $uri);
        foreach ($expectedParts as $part => $expected) {
            $this->assertSame($expected, $uri->{"get$part"}());
        }
    }

    /**
     * @return array<string,array{string,string,2?:array<string,string|int|null>,3?:bool}>
     */
    public static function normalisationProvider(): array
    {
        $encodedUnreserved = implode(array_map(
            fn(int $codepoint): string => sprintf('%%%02x', $codepoint),
            [
                ord('-'),
                ord('.'),
                ord('_'),
                ord('~'),
                ...range(ord('0'), ord('9')),
                ...range(ord('A'), ord('Z')),
                ...range(ord('a'), ord('z')),
            ]
        ));
        $unreserved = '-._~0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $unreservedAndDelims = $unreserved . '!$&\'()*+,:;=@';

        return [
            'encoded unreserved -> decoded unreserved #1' => [
                "/$unreserved",
                "/$encodedUnreserved",
            ],
            'encoded unreserved -> decoded unreserved #2' => [
                '/path?q=value#fragment',
                '/p%61th?q=v%61lue#fr%61gment',
                [
                    'path' => '/path',
                    'query' => 'q=value',
                    'fragment' => 'fragment',
                ],
            ],
            'schema + host -> lowercase' => [
                'http://example.com/Path/To/Resource',
                'HTTP://EXAMPLE.COM/Path/To/Resource',
            ],
            'schema + encoded host -> lowercase' => [
                'http://example.com/Path/To/Resource',
                'HTTP://%45%58%41%4d%50%4c%45.COM/Path/To/Resource',
            ],
            'encoded slash' => [
                '/segment%2Fwith%2Fembedded/slash',
                '/segment%2fwith%2fembedded/slash',
            ],
            'encoded space' => [
                '/pa%20th?q=va%20lue#frag%20ment',
                '/pa%20th?q=va%20lue#frag%20ment',
                [
                    'path' => '/pa%20th',
                    'query' => 'q=va%20lue',
                    'fragment' => 'frag%20ment',
                ],
            ],
            'unencoded space' => [
                '/pa%20th?q=va%20lue#frag%20ment',
                '/pa th?q=va lue#frag ment',
                [
                    'path' => '/pa%20th',
                    'query' => 'q=va%20lue',
                    'fragment' => 'frag%20ment',
                ],
                false,
            ],
            'unencoded multibyte' => [
                '/%E2%82%AC?%E2%82%AC#%E2%82%AC',
                '/€?€#€',
                [
                    'path' => '/%E2%82%AC',
                    'query' => '%E2%82%AC',
                    'fragment' => '%E2%82%AC',
                ],
                false,
            ],
            'invalid encoding' => [
                '/pa%252-th?q=va%252-lue#frag%252-ment',
                '/pa%2-th?q=va%2-lue#frag%2-ment',
                [
                    'path' => '/pa%252-th',
                    'query' => 'q=va%252-lue',
                    'fragment' => 'frag%252-ment',
                ],
                false,
            ],
            'path segments' => [
                '/pa/th//two?q=va/lue#frag/ment',
                '/pa/th//two?q=va/lue#frag/ment',
                [
                    'path' => '/pa/th//two',
                    'query' => 'q=va/lue',
                    'fragment' => 'frag/ment',
                ],
            ],
            'unreserved + delimiters' => [
                "/$unreservedAndDelims?$unreservedAndDelims#$unreservedAndDelims",
                "/$unreservedAndDelims?$unreservedAndDelims#$unreservedAndDelims",
                [
                    'path' => "/$unreservedAndDelims",
                    'query' => $unreservedAndDelims,
                    'fragment' => $unreservedAndDelims,
                ],
            ],
        ];
    }

    public function testNormalisationWithScheme(): void
    {
        $uri = (new Uri('//example.com'))->withScheme('HTTP');

        self::assertSame('http', $uri->getScheme());
        self::assertSame('http://example.com', (string) $uri);
    }

    public function testNormalisationWithHost(): void
    {
        $uri = (new Uri())->withHost('eXaMpLe.CoM');

        self::assertSame('example.com', $uri->getHost());
        self::assertSame('//example.com', (string) $uri);
    }

    public function testWithPort(): void
    {
        $uri = new Uri('https://example.com:443');
        self::assertNull($uri->getPort());
        self::assertSame('example.com', $uri->getAuthority());

        $uri = (new Uri('https://example.com'))->withPort(443);
        self::assertNull($uri->getPort());
        self::assertSame('example.com', $uri->getAuthority());

        $uri = new Uri('http://example.com:80');
        self::assertNull($uri->getPort());
        self::assertSame('example.com', $uri->getAuthority());

        $uri = (new Uri('http://example.com'))->withPort(80);
        self::assertNull($uri->getPort());
        self::assertSame('example.com', $uri->getAuthority());

        $uri = (new Uri('//example.com'))->withPort(80);
        self::assertSame(80, $uri->getPort());
        self::assertSame('example.com:80', $uri->getAuthority());

        $uri = new Uri('http://example.com:443');
        self::assertSame('http', $uri->getScheme());
        self::assertSame(443, $uri->getPort());

        $uri = $uri->withScheme('https');
        self::assertNull($uri->getPort());

        $uri = (new Uri('http://example.com:8080'))->withPort(null);
        self::assertNull($uri->getPort());
        self::assertSame('http://example.com', (string) $uri);
    }

    public function testWithUserInfoWithoutHost(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('URI without host cannot have userinfo or port');
        $uri = (new Uri())->withUserInfo('user', 'pass');
    }

    public function testWithPortWithoutHost(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('URI without host cannot have userinfo or port');
        $uri = (new Uri())->withPort(8080);
    }

    public function testWithEncoding(): void
    {
        $uri = (new Uri())->withPath('/baz?#€/b%61r');
        self::assertSame('/baz%3F%23%E2%82%AC/bar', $uri->getPath());
        self::assertSame('/baz%3F%23%E2%82%AC/bar', (string) $uri);

        $uri = (new Uri())->withQuery('?=#&€=/&b%61r');
        self::assertSame('?=%23&%E2%82%AC=/&bar', $uri->getQuery());
        self::assertSame('??=%23&%E2%82%AC=/&bar', (string) $uri);

        $uri = (new Uri())->withFragment('#€?/b%61r');
        self::assertSame('%23%E2%82%AC?/bar', $uri->getFragment());
        self::assertSame('#%23%E2%82%AC?/bar', (string) $uri);

        $uri = (new Uri())
            ->withHost('example.com')
            ->withUserInfo('foo@bar.com', 'pass#word');
        self::assertSame('foo%40bar.com:pass%23word', $uri->getUserInfo());

        $uri = (new Uri())
            ->withHost('example.com')
            ->withUserInfo('foo%40bar.com', 'pass%23word');
        self::assertSame('foo%40bar.com:pass%23word', $uri->getUserInfo());

        $uri = (new Uri())
            ->withHost('example.com')
            ->withUserInfo('foo@:bar:', 'pass:#word');
        self::assertSame('foo%40%3Abar%3A:pass%3A%23word', $uri->getUserInfo());
    }

    public function testRelativePathWithAuthority(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path must be empty or begin with "/" in URI with authority');
        (new Uri())->withHost('example.com')->withPath('foo');
    }

    public function testPathWithTwoSlashesWithoutAuthority(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path cannot begin with "//" in URI without authority');
        (new Uri())->withPath('//foo');
    }

    public function testPathWithTwoSlashes(): void
    {
        $uri = new Uri('http://example.org//path-not-host.com');
        self::assertSame('//path-not-host.com', $uri->getPath());

        $uri = $uri->withScheme('');
        self::assertSame('//example.org//path-not-host.com', (string) $uri);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path cannot begin with "//" in URI without authority');
        $uri->withHost('');
    }

    public function testRelativePathWithColonSegmentWithoutScheme(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path cannot begin with colon segment in URI without scheme');
        (new Uri())->withPath('mailto:foo');
    }

    public function testRelativePathWithColonSegment(): void
    {
        $uri = (new Uri('urn:/mailto:foo'))->withScheme('');
        self::assertSame('/mailto:foo', $uri->getPath());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path cannot begin with colon segment in URI without scheme');
        (new Uri('urn:mailto:foo'))->withScheme('');
    }

    public function testInitialValues(): void
    {
        $uri = new Uri();

        self::assertSame('', $uri->getScheme());
        self::assertSame('', $uri->getAuthority());
        self::assertSame('', $uri->getUserInfo());
        self::assertSame('', $uri->getHost());
        self::assertNull($uri->getPort());
        self::assertSame('', $uri->getPath());
        self::assertSame('', $uri->getQuery());
        self::assertSame('', $uri->getFragment());
    }

    public function testImmutability(): void
    {
        $uri = new Uri();

        self::assertNotSame($uri, $uri->withScheme('https'));
        self::assertNotSame($uri, $uri2 = $uri->withHost('example.com'));
        self::assertNotSame($uri2, $uri2->withUserInfo('user', 'pass'));
        self::assertNotSame($uri2, $uri2->withPort(8080));
        self::assertNotSame($uri, $uri->withPath('/path/123'));
        self::assertNotSame($uri, $uri->withQuery('q=abc'));
        self::assertNotSame($uri, $uri->withFragment('test'));
    }

    public function testIPv6Host(): void
    {
        $uri = new Uri('https://[2a00:f48:1008::212:183:10]');
        self::assertSame('[2a00:f48:1008::212:183:10]', $uri->getHost());

        $uri = new Uri('http://[2a00:f48:1008::212:183:10]:56?foo=bar');
        self::assertSame('[2a00:f48:1008::212:183:10]', $uri->getHost());
        self::assertSame(56, $uri->getPort());
        self::assertSame('foo=bar', $uri->getQuery());
    }
}

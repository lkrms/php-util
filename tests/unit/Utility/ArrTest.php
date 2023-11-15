<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility;

use Lkrms\Utility\Arr;

final class ArrTest extends \Lkrms\Tests\TestCase
{
    /**
     * @dataProvider implodeProvider
     *
     * @param mixed[] $array
     */
    public function testImplode(string $expected, string $separator, array $array): void
    {
        $this->assertSame($expected, Arr::implode($separator, $array));
    }

    /**
     * @return array<array{string,string,mixed[]}>
     */
    public static function implodeProvider(): array
    {
        return [
            [
                '0,a, ,-1,0,1,3.14,1',
                ',',
                ['0', '', 'a', ' ', -1, 0, 1, false, 3.14, null, true],
            ],
            [
                '0 a   -1 0 1 3.14 1',
                ' ',
                ['0', '', 'a', ' ', -1, 0, 1, false, 3.14, null, true],
            ],
            [
                '',
                ' ',
                [],
            ],
            [
                '',
                ' ',
                ['', false, null],
            ],
        ];
    }
}

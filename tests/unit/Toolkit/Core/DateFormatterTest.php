<?php declare(strict_types=1);

namespace Salient\Tests\Core;

use Salient\Core\DateFormatter;
use Salient\Core\DotNetDateParser;
use Salient\Tests\TestCase;
use DateTimeInterface;

/**
 * @covers \Salient\Core\DateFormatter
 */
final class DateFormatterTest extends TestCase
{
    public function testDotNet(): void
    {
        $formatter = new DateFormatter(DateTimeInterface::RFC3339_EXTENDED, null, new DotNetDateParser());
        $formatter2 = new DateFormatter(DateTimeInterface::RFC3339_EXTENDED, 'Australia/Sydney', new DotNetDateParser());

        $data = [
            '/Date(1530144000000+0530)/',
            '/Date(1603152000000)/',
            '/Date(1668143569876+1100)/'
        ];

        $this->assertEquals(
            [
                '2018-06-28T05:30:00.000+05:30',
                '2020-10-20T00:00:00.000+00:00',
                '2022-11-11T16:12:49.876+11:00',
            ],
            array_map(
                fn(string $date) =>
                    $formatter->format($formatter->parse($date)),
                $data
            )
        );

        $this->assertEquals(
            [
                '2018-06-28T10:00:00.000+10:00',
                '2020-10-20T11:00:00.000+11:00',
                '2022-11-11T16:12:49.876+11:00',
            ],
            array_map(
                fn(string $date) =>
                    $formatter2->format($formatter2->parse($date)),
                $data
            )
        );
    }
}

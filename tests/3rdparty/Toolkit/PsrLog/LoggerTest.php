<?php declare(strict_types=1);

namespace Salient\Tests\PsrLog;

use Psr\Log\Test\LoggerInterfaceTest;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Salient\Console\Target\MockTarget;
use Salient\Console\ConsoleFormatter;
use Salient\Console\ConsoleWriter;
use Salient\Contract\Core\MessageLevel as Level;
use Salient\Utility\Str;

/**
 * @covers \Salient\Console\ConsoleLogger
 * @covers \Salient\Console\ConsoleWriter
 */
final class LoggerTest extends LoggerInterfaceTest
{
    private MockTarget $Target;

    /**
     * @inheritDoc
     */
    public function getLogger(): LoggerInterface
    {
        return (new ConsoleWriter())
            ->registerTarget($this->Target = new MockTarget(
                null,
                true,
                true,
                true,
                null,
                new ConsoleFormatter(null, null, fn() => null, [], []),
            ))
            ->getLogger();
    }

    /**
     * @return string[]
     */
    public function getLogs(): array
    {
        foreach ($this->Target->getMessages() as [$level, $message]) {
            $logs[] = sprintf('%s %s', Str::lower(Level::toName($level)), $message);
        }
        return $logs ?? [];
    }

    /**
     * @inheritDoc
     */
    public function testThrowsOnInvalidLevel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        parent::testThrowsOnInvalidLevel();
    }
}

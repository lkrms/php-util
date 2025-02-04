<?php declare(strict_types=1);

namespace Salient\Tests\Core;

use Salient\Contract\Core\MessageLevel as Level;
use Salient\Contract\Core\MessageLevelGroup as LevelGroup;
use Salient\Core\Facade\Console;
use Salient\Core\AbstractMultipleErrorException;
use Salient\Testing\Console\MockTarget;
use Salient\Tests\TestCase;

/**
 * @covers \Salient\Core\AbstractMultipleErrorException
 * @covers \Salient\Core\Concern\MultipleErrorExceptionTrait
 */
final class AbstractMultipleErrorExceptionTest extends TestCase
{
    private MockTarget $ConsoleTarget;

    protected function setUp(): void
    {
        $this->ConsoleTarget = new MockTarget();
        Console::registerTarget($this->ConsoleTarget, LevelGroup::ALL_EXCEPT_DEBUG);
    }

    protected function tearDown(): void
    {
        Console::unload();
    }

    public function testConstructor(): void
    {
        $exception = new MyAbstractMultipleErrorException('ohno:');
        $this->assertSame('ohno', $exception->getMessage());
        $this->assertNull($exception->getPrevious());
        $this->assertNull($exception->getExitStatus());
        $this->assertSame('ohno', $exception->getMessageOnly());
        $this->assertSame([], $exception->getErrors());
        $this->assertFalse($exception->hasUnreportedErrors());

        $exception = new MyAbstractMultipleErrorException('ohno:', 'error');
        $this->assertSame('ohno: error', $exception->getMessage());
        $this->assertSame('ohno', $exception->getMessageOnly());
        $this->assertSame(['error'], $exception->getErrors());
        $this->assertTrue($exception->hasUnreportedErrors());

        $exception = new MyAbstractMultipleErrorException('ohno', 'error1', "error2\nerror2, line2");
        $this->assertSame("ohno:\n- error1\n- error2\n  error2, line2", $exception->getMessage());
        $this->assertSame('ohno', $exception->getMessageOnly());
        $this->assertSame(['error1', "error2\nerror2, line2"], $exception->getErrors());
        $this->assertTrue($exception->hasUnreportedErrors());
        $exception->reportErrors(Console::getInstance());
        $this->assertFalse($exception->hasUnreportedErrors());
        $this->assertSameConsoleMessages([
            [Level::ERROR, 'Error: error1'],
            [Level::ERROR, "Error:\n  error2\n  error2, line2"],
        ], $this->ConsoleTarget->getMessages());
    }
}

class MyAbstractMultipleErrorException extends AbstractMultipleErrorException {}

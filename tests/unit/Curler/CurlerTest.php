<?php declare(strict_types=1);

namespace Lkrms\Tests\Curler;

use Lkrms\Curler\Exception\CurlerCurlErrorException;
use Lkrms\Curler\Exception\CurlerHttpErrorException;
use Lkrms\Curler\Curler;
use Lkrms\Tests\TestCase;

final class CurlerTest extends TestCase
{
    public function testCurlError(): void
    {
        $this->expectException(CurlerCurlErrorException::class);

        (new Curler('http://<localhost>/path'))->get();
    }

    public function testHttpError(): void
    {
        $this->expectException(CurlerHttpErrorException::class);

        (new Curler('http://localhost:3001'))->get();
    }
}

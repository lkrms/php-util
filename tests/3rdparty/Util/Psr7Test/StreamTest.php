<?php declare(strict_types=1);

namespace Lkrms\Tests\Psr7Test;

use Http\Psr7Test\StreamIntegrationTest;
use Psr\Http\Message\StreamInterface;
use Salient\Http\Stream;

class StreamTest extends StreamIntegrationTest
{
    public function createStream($data)
    {
        if ($data instanceof StreamInterface) {
            return $data;
        }
        if (is_string($data)) {
            return Stream::fromString($data);
        }
        return new Stream($data);
    }
}

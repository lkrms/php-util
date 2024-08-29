<?php declare(strict_types=1);

namespace Salient\Contract\Curler\Event;

use Psr\Http\Message\RequestInterface;

/**
 * Dispatched before a cURL request is executed
 *
 * @api
 */
interface CurlRequestEventInterface extends CurlEventInterface
{
    /**
     * Get the request being sent to the endpoint
     */
    public function getRequest(): RequestInterface;
}

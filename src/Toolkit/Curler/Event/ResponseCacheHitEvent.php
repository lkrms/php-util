<?php declare(strict_types=1);

namespace Salient\Curler\Event;

use Psr\Http\Message\RequestInterface;
use Salient\Contract\Curler\Event\ResponseCacheHitEventInterface;
use Salient\Contract\Curler\CurlerInterface;
use Salient\Contract\Http\HttpResponseInterface;

/**
 * @internal
 */
class ResponseCacheHitEvent extends AbstractCurlerEvent implements ResponseCacheHitEventInterface
{
    protected RequestInterface $Request;
    protected HttpResponseInterface $Response;

    public function __construct(CurlerInterface $curler, RequestInterface $request, HttpResponseInterface $response)
    {
        $this->Request = $request;
        $this->Response = $response;

        parent::__construct($curler);
    }

    public function getRequest(): RequestInterface
    {
        return $this->Request;
    }

    public function getResponse(): HttpResponseInterface
    {
        return $this->Response;
    }
}
<?php declare(strict_types=1);

namespace Salient\Curler\Exception;

use Psr\Http\Message\RequestInterface;
use Salient\Contract\Http\HttpResponseInterface;
use Salient\Core\Utility\Arr;
use Throwable;

class HttpErrorException extends AbstractCurlerException
{
    protected int $StatusCode;

    /**
     * @param array<string,mixed> $data
     */
    public function __construct(
        RequestInterface $request,
        HttpResponseInterface $response,
        array $data = [],
        ?Throwable $previous = null
    ) {
        $this->StatusCode = $response->getStatusCode();

        parent::__construct(
            sprintf('HTTP error %s', Arr::implode(' ', [
                (string) $this->StatusCode,
                $response->getReasonPhrase(),
            ])),
            $request,
            $response,
            $data,
            $previous,
        );
    }

    /**
     * Get the request that triggered the exception
     */
    public function getRequest(): RequestInterface
    {
        return $this->Request;
    }

    /**
     * Get the response that triggered the exception
     */
    public function getResponse(): HttpResponseInterface
    {
        return $this->Response;
    }

    /**
     * Get the exception's underlying HTTP status code
     */
    public function getStatusCode(): int
    {
        return $this->StatusCode;
    }

    /**
     * Check if the exception's underlying HTTP status code is 404 (Not Found)
     * or 410 (Gone)
     */
    public function isNotFound(): bool
    {
        return $this->StatusCode === 404 || $this->StatusCode === 410;
    }
}

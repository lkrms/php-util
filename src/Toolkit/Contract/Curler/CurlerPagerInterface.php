<?php declare(strict_types=1);

namespace Salient\Contract\Curler;

use Psr\Http\Message\RequestInterface;
use Salient\Contract\Http\HttpResponseInterface;

/**
 * @api
 */
interface CurlerPagerInterface
{
    /**
     * Get a request to retrieve the first page of data from the endpoint
     *
     * Return `$request` if no special handling is required.
     *
     * @param mixed[]|null $query The query applied to `$request`.
     * @return CurlerPageRequestInterface|RequestInterface
     */
    public function getFirstRequest(
        RequestInterface $request,
        CurlerInterface $curler,
        ?array $query = null
    );

    /**
     * Convert data returned by the endpoint to a new page object
     *
     * @param mixed $data
     * @param mixed[]|null $query The query applied to `$request` or returned by
     * {@see CurlerPageRequestInterface::getNextQuery()}, if applicable.
     */
    public function getPage(
        $data,
        RequestInterface $request,
        HttpResponseInterface $response,
        CurlerInterface $curler,
        ?CurlerPageInterface $previousPage = null,
        ?array $query = null
    ): CurlerPageInterface;
}

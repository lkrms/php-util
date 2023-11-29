<?php declare(strict_types=1);

namespace Lkrms\Http\Catalog;

use Lkrms\Concept\Dictionary;

/**
 * Groups of HTTP request methods
 *
 * @extends Dictionary<array<HttpRequestMethod::*>>
 */
final class HttpRequestMethodGroup extends Dictionary
{
    /**
     * @var array<HttpRequestMethod::*>
     */
    public const ALL = [
        HttpRequestMethod::GET,
        HttpRequestMethod::HEAD,
        HttpRequestMethod::POST,
        HttpRequestMethod::PUT,
        HttpRequestMethod::PATCH,
        HttpRequestMethod::DELETE,
        HttpRequestMethod::CONNECT,
        HttpRequestMethod::OPTIONS,
        HttpRequestMethod::TRACE,
    ];
}
<?php declare(strict_types=1);

namespace Salient\Contract\Http;

use Psr\Http\Message\ServerRequestInterface;

/** @api */
interface HttpServerRequestInterface extends HttpRequestInterface, ServerRequestInterface {}

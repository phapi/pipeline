<?php
/**
 * This file is part of Phapi.
 *
 * See license.md for information about the license.
 */

namespace Phapi\Tests\Fixtures;

use Phapi\Contract\Middleware\ErrorMiddleware;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;


/**
 * Class One
 *
 * @category Phapi
 * @package  Phapi\Middleware
 * @author   Peter Ahinko <peter@ahinko.se>
 * @license  MIT (http://opensource.org/licenses/MIT)
 * @link     https://github.com/ahinko/phapi
 */
class ErrorMiddlewareObject implements ErrorMiddleware {

    public function __invoke(
        Request $request,
        Response $response,
        callable $next = null
    ) {
        $response = $next($request, $response, $next);

        $response = $response->withAddedHeader('exception', 'caught');
        return $response;
    }

    public function setContainer($container = null) {
        $this->container = $container;
    }

}
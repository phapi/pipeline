# Phapi Middleware Pipeline

Phapi relies heavily on middleware. Almost everything is a middleware: routing, content negotiation and many other things.

## Why middleware?
The benefits are many. Middleware are easy to replace and from a performance perspective they can, if implemented right, bypass a lot of code that doesn't need to run in specific circumstances, for example, if a client hits their rate limit there is no point in executing routing, dispatching etc.

## Installation
The package is installed by default by the Phapi framework. Installing the package to use is separately can be done by using composer:

```shell
$ composer require phapi/pipeline:1.*
```

## Usage
Attaching a new middleware to the pipeline are done by using the <code>pipe()</code> method.

```php
<?php
$pipeline = new Phapi\Middleware\Pipeline();
$pipeline->pipe(new Middleware\Cors());
```

Please note that middleware's will be called in the order they are attached.

A middleware CAN implement the Middleware Interface, but MUST be callable. All middleware WILL be called with three parameters: Request, Response and Next. A middleware should invoke the <code>$next</code> variable and pass the <code>$request</code>, <code>$response</code> and <code>$next</code> parameters. A middleware must also return a response.

Once the queue is empty the resulted response instance will be returned.

## Error handling
Implementing the ErrorMiddleware interface enables the pipeline to be able to handle errors. The middleware must take care of the error handling and call the <code>prepareErrorQueue()</code> method. This will trigger a reset in the pipeline and only the middleware added before (usually serializer middleware and a middleware sending the response to the client) the ErrorMiddleware will be called.


## Create your own middleware class

Middleware accepts a request and a response and a callback (<code>$next</code>) that is called if the middleware allows further middleware to process the request. If a middleware doesn't need or desire to allow further processing it should not call the callback (<code>$next</code>) and should instead return a response. Note that <code>$next</code> can be <code>null</code>.

If you implement a method named <code>setContainer()</code> the pipeline will call that method and provide the Dependency Injector Container that can be used in the <code>__invoke()</code> method.

**Example:**

```php
<?php
  public function setContainer($container)
  {
    $this->container = $container;
  }
```

The main task of a middleware is to process an incoming request and/or the response. The middleware must accept a request and a response (PSR-7 compatible) instance and do something with them.

The middleware must pass a request and a response to the ($next) callback and finally either return the response from the ($next) callback or by modifying the response before returning it.

**Examples:**
```php
<?php
  public function __invoke(
    Request $request,
    Response $response,
    callable $next)
  {
      // Do something ...

      return $next($request, $response, $next);
  }
```

**OR**

```php
<?php
  public function __invoke(
    Request $request,
    Response $response,
    callable $next)
  {
      // Do something ...

      $response = $next($request, $response, $next);

      // Modify response ...

      return $response;
  }
```

If the middleware should break the queue, example: the client hit the rate limit, it should return a response instead of invoking <code>$next</code>.

**Example:**

```php
<?php
  public function __invoke(
    Request $request,
    Response $response,
    callable $next)
  {
      if ($this->tooManyRequests()) {
        // Set appropriate headers and body

        // Return response
        return $response;
      }

      return $next($request, $response, $next);
  }
```

### Complete example
```php
<?php

namespace Phapi\Middleware\Example;

use Phapi\Contract\Di\Container;
use Phapi\Contract\Middleware\Middleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Eample middleware
 *
 * @category Phapi
 * @package  Phapi\Middleware\Example
 * @author   Peter Ahinko <peter@ahinko.se>
 * @license  MIT (http://opensource.org/licenses/MIT)
 * @link     https://github.com/phapi/middleware-example
 */
class Example implements Middleware
{

    /**
     * Dependency injection container
     *
     * @var Container
     */
    private $container;

    /**
     * Set dependency injection container
     *
     * @param Container $container
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Invoking the middleware
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param callable $next
     * @return ResponseInterface $response
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next = null)
    {
        // Do something ...

        // Call next middleware
        $response = $next($request, $response, $next);

        // Do something with the response ...

        // Return the response
        return $response;
    }
}

```

## License
Phapi Middleware Pipeline is licensed under the MIT License - see the [license.md](https://github.com/phapi/pipeline/blob/master/license.md) file for details

## Contribute
Contribution, bug fixes etc are [always welcome](https://github.com/phapi/pipeline/issues/new).

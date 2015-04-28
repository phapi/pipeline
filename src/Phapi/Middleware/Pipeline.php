<?php

namespace Phapi\Middleware;

use Phapi\Contract\Middleware\ErrorMiddleware;
use Phapi\Contract\Middleware\Pipeline as PipelineContract;
use Phapi\Contract\Middleware\SerializerMiddleware;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * Class Pipeline
 *
 * This class implements a pipeline of middleware, which can
 * be attached using the 'pipe()'-method.
 *
 * @category Phapi
 * @package  Phapi\Middleware
 * @author   Peter Ahinko <peter@ahinko.se>
 * @license  MIT (http://opensource.org/licenses/MIT)
 * @link     https://github.com/phapi/pipeline
 */
class Pipeline implements PipelineContract {

    /**
     * Queue of middleware
     *
     * @var \SplQueue
     */
    protected $queue;

    /**
     * Queue of middleware that handles errors
     *
     * @var \SplQueue
     */
    protected $errorQueue;

    /**
     * Lock for the error queue
     *
     * @var boolean
     */
    protected $errorQueueLocked;

    /**
     * Dependency Injector Container
     *
     * @var mixed
     */
    protected $container;

    /**
     * Lock status of the pipeline
     *
     * @var bool
     */
    protected $locked = false;

    public function __construct($container = null)
    {
        $this->queue = new \SplQueue();
        $this->errorQueue = new \SplQueue();
        $this->errorQueueLocked = false;
        $this->container = $container;
    }

    /**
     * Add middleware to the pipe-line. Please note that middleware's will
     * be called in the order they are added.
     *
     * A middleware CAN implement the Middleware Interface, but MUST be
     * callable. A middleware WILL be called with three parameters:
     * Request, Response and Next.
     *
     * @throws \RuntimeException when adding middleware to the stack to late
     * @param callable $middleware
     */
    public function pipe(callable $middleware)
    {
        // Check if the pipeline is locked
        if ($this->locked) {
            throw new \RuntimeException('Middleware can’t be added once the stack is dequeuing');
        }

        // Inject the dependency injection container
        if (method_exists($middleware, 'setContainer') && $this->container !== null) {
            $middleware->setContainer($this->container);
        }

        // Force serializers to register mime types
        if ($middleware instanceof SerializerMiddleware) {
            $middleware->registerMimeTypes();
        }

        // Add the middleware to the queue
        $this->queue->enqueue($middleware);

        // Check if middleware should be added to the error queue
        if (!$this->errorQueueLocked) {
            $this->errorQueue->enqueue($middleware);

            // Check if we should lock the error queue
            if ($middleware instanceof ErrorMiddleware) {
                $this->errorQueueLocked = true;
            }
        }
    }

    /**
     * The method is used by the registered error handler/middleware to
     * reset the queue to only use the middleware registered before the
     * error middleware. This is done so that the serializer and courier
     * middleware can be called.
     *
     * After the reset is done the (new error) queue is (re)started.
     */
    public function prepareErrorQueue()
    {
        $this->queue = $this->errorQueue;
    }

    /**
     * Handle the request by calling the next middleware in the queue.
     *
     * The method requires that a request and a response are provided.
     * These will be passed to any middleware invoked.
     *
     * Once the queue is empty the resulted response instance will be
     * returned.
     *
     * @param Request $request
     * @param Response $response
     * @param callable $next
     * @return Response
     */
    public function __invoke(Request $request, Response $response, callable $next = null)
    {
        // Lock the pipeline
        $this->locked = true;

        // Update container with latest request and response
        $this->container['latestRequest'] = $request;
        $this->container['latestResponse'] = $response;

        // Check if the pipe-line is broken or if we are at the end of the queue
        if (!$this->queue->isEmpty()) {
            // Pick the next middleware from the queue
            $next = $this->queue->dequeue();

            // Call the next middleware (if callable)
            return (is_callable($next)) ? $next($request, $response, $this) : $response;
        }

        // Nothing left to do, return the response
        return $response;
    }
}
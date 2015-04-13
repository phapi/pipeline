<?php
namespace Phapi\Tests;

use Phapi\Middleware\Pipeline;
use Phapi\Tests\Fixtures\ErrorMiddlewareObject;
use Phapi\Tests\Fixtures\MiddlewareObject;
use PHPUnit_Framework_TestCase as TestCase;

/**
 * @coversDefaultClass \Phapi\Middleware\Pipeline
 */
class PipelineTest extends TestCase
{

    public function testConstructor()
    {
        $mockContainer = \Mockery::mock('Phapi\Contract\Di\Container');
        $mockContainer->shouldReceive('offsetSet');

        $mockRequest = \Mockery::mock('Psr\Http\Message\ServerRequestInterface');

        $mockResponse = \Mockery::mock('Psr\Http\Message\ResponseInterface');
        $mockResponse->shouldReceive('hasHeader')->with('X-Foo')->andReturn(true);
        $mockResponse->shouldReceive('getHeader')->with('X-Foo')->andReturn('modified');
        $mockResponse->shouldReceive('getStatusCode')->andReturn(500);
        $mockResponse->shouldReceive('withStatus')->andReturnSelf();
        $mockResponse->shouldReceive('withAddedHeader')->withAnyArgs()->andReturnSelf();

        $pipeline = new Pipeline($mockContainer);
        $pipeline->pipe(new MiddlewareObject());

        $pipeline->pipe(function ($mockRequest, $mockResponse, $next) {
            $mockResponse = $next($mockRequest, $mockResponse, $next);
            return $mockResponse->withStatus(500);
        });
        $mockResponse = $pipeline($mockRequest, $mockResponse, $pipeline);

        $this->assertTrue($mockResponse->hasHeader('X-Foo'));
        $this->assertEquals('modified', $mockResponse->getHeader('X-Foo'));
        $this->assertSame(500, $mockResponse->getStatusCode());
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Middleware can’t be added once the stack is dequeuing
     */
    public function testLock()
    {
        $mockContainer = \Mockery::mock('Phapi\Contract\Di\Container');
        $mockContainer->shouldReceive('offsetSet');

        $mockRequest = \Mockery::mock('Psr\Http\Message\ServerRequestInterface');

        $mockResponse = \Mockery::mock('Psr\Http\Message\ResponseInterface');
        $mockResponse->shouldReceive('hasHeader')->with('X-Foo')->andReturn(true);
        $mockResponse->shouldReceive('getHeader')->with('X-Foo')->andReturn('modified');
        $mockResponse->shouldReceive('getStatusCode')->andReturn(500);
        $mockResponse->shouldReceive('withStatus')->andReturnSelf();
        $mockResponse->shouldReceive('withAddedHeader')->withAnyArgs()->andReturnSelf();

        $pipeline = new Pipeline($mockContainer);
        $pipeline->pipe(new MiddlewareObject());
        $pipeline->pipe(function ($request, $response, $next) use ($pipeline) {
            $pipeline->pipe(new MiddlewareObject());
            $response = $next($request, $response, $next);
            return $response->withStatus(500);
        });
        $response = $pipeline($mockRequest, $mockResponse, $pipeline);
    }

    public function testErrorMiddleware()
    {
        $mockContainer = \Mockery::mock('Phapi\Contract\Di\Container');
        $mockContainer->shouldReceive('offsetSet');

        $mockRequest = \Mockery::mock('Psr\Http\Message\ServerRequestInterface');

        $mockResponse = \Mockery::mock('Psr\Http\Message\ResponseInterface');
        $mockResponse->shouldReceive('hasHeader')->with('X-Foo' || 'Exception')->andReturn(true);

        $mockResponse->shouldReceive('getHeader')->with('X-Foo' || 'Exception')->andReturn('modified', 'caught');
        $mockResponse->shouldReceive('getStatusCode')->andReturn(500);
        $mockResponse->shouldReceive('withStatus')->andReturnSelf();
        $mockResponse->shouldReceive('withAddedHeader')->withAnyArgs()->andReturnSelf();

        $pipeline = new Pipeline($mockContainer);
        $pipeline->pipe(new MiddlewareObject());
        $pipeline->pipe(new ErrorMiddlewareObject());

        $pipeline->prepareErrorQueue();

        $response = $pipeline($mockRequest, $mockResponse, $pipeline);

        $this->assertTrue($response->hasHeader('X-Foo'));
        $this->assertEquals('modified', $response->getHeader('X-Foo'));
        $this->assertTrue($response->hasHeader('exception'));
        $this->assertEquals('caught', $response->getHeader('exception'));
    }
}
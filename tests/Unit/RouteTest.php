<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Triangle\Router;
use Triangle\Router\Dispatcher;

class RouteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Router::clear();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Router::clear();
    }

    public function testRouteRegistration(): void
    {
        Router::get('/test', function () {
            return 'test';
        });

        $routeInfo = Router::dispatch('GET', '/test');
        
        $this->assertEquals(Dispatcher::FOUND, $routeInfo[0]);
        $this->assertIsCallable($routeInfo[1]);
    }

    public function testRouteWithParameters(): void
    {
        Router::get('/user/{id}', function ($id) {
            return $id;
        });

        $routeInfo = Router::dispatch('GET', '/user/123');
        
        $this->assertEquals(Dispatcher::FOUND, $routeInfo[0]);
        $this->assertEquals(['123'], $routeInfo[2]);
    }

    public function testRouteNotFound(): void
    {
        $routeInfo = Router::dispatch('GET', '/non-existent');
        
        $this->assertEquals(Dispatcher::NOT_FOUND, $routeInfo[0]);
    }

    public function testMethodNotAllowed(): void
    {
        Router::get('/test', function () {
            return 'test';
        });

        $routeInfo = Router::dispatch('POST', '/test');
        
        $this->assertEquals(Dispatcher::METHOD_NOT_ALLOWED, $routeInfo[0]);
    }
}


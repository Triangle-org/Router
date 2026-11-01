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
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function testRouteRegistration(): void
    {
        Router::get('/tests/route-registration', function () {
            return 'test';
        });

        $routeInfo = Router::dispatch('GET', '/tests/route-registration');
        
        $this->assertEquals(Dispatcher::FOUND, $routeInfo[0]);
        $this->assertIsCallable($routeInfo[1]);
    }

    public function testRouteWithParameters(): void
    {
        Router::get('/tests/users/{id}', function ($id) {
            return $id;
        });

        $routeInfo = Router::dispatch('GET', '/tests/users/123');
        
        $this->assertEquals(Dispatcher::FOUND, $routeInfo[0]);
        $this->assertEquals(['123'], $routeInfo[2]);
    }

    public function testRouteNotFound(): void
    {
        $routeInfo = Router::dispatch('GET', '/tests/not-found');
        
        $this->assertEquals(Dispatcher::NOT_FOUND, $routeInfo[0]);
    }

    public function testMethodNotAllowed(): void
    {
        Router::get('/tests/method-not-allowed', function () {
            return 'test';
        });

        $routeInfo = Router::dispatch('POST', '/tests/method-not-allowed');
        
        $this->assertEquals(Dispatcher::METHOD_NOT_ALLOWED, $routeInfo[0]);
    }
}


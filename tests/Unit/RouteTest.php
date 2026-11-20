<?php

/**
 * @package     Triangle Router Component
 * @link        https://github.com/Triangle-org/Router
 *
 * @author      Ivan Zorin <creator@localzet.com>
 * @copyright   Copyright © 2016-2026 Localzet Group. All rights reserved.
 * @license     https://www.gnu.org/licenses/agpl-3.0 GNU Affero General Public License v3.0
 *
 *              This program is free software: you can redistribute it and/or modify
 *              it under the terms of the GNU Affero General Public License as published
 *              by the Free Software Foundation, either version 3 of the License, or
 *              (at your option) any later version.
 *
 *              This program is distributed in the hope that it will be useful,
 *              but WITHOUT ANY WARRANTY; without even the implied warranty of
 *              MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *              GNU Affero General Public License for more details.
 *
 *              You should have received a copy of the GNU Affero General Public License
 *              along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Triangle\Router;
use Triangle\Router\Dispatcher;

class RouteTest extends TestCase
{
    private function dispatch(string $method, string $path): array
    {
        Router::collect([]);
        return Router::dispatch($method, $path);
    }

    public function testChildCallbackCanBeOmitted(): void
    {
        $child = Router::child('/optional');

        $this->assertInstanceOf(Router::class, $child);
    }

    public function testRouteRegistration(): void
    {
        Router::get('/tests/route-registration', function () {
            return 'test';
        });

        $routeInfo = $this->dispatch('GET', '/tests/route-registration');
        
        $this->assertEquals(Dispatcher::FOUND, $routeInfo[0]);
        $this->assertIsCallable($routeInfo[1]);
    }

    public function testRouteWithParameters(): void
    {
        Router::get('/tests/users/{id}', function ($id) {
            return $id;
        });

        $routeInfo = $this->dispatch('GET', '/tests/users/123');
        
        $this->assertEquals(Dispatcher::FOUND, $routeInfo[0]);
        $this->assertEquals(['id' => '123'], $routeInfo[2]);
    }

    public function testRouteNotFound(): void
    {
        $routeInfo = $this->dispatch('GET', '/tests/not-found');
        
        $this->assertEquals(Dispatcher::NOT_FOUND, $routeInfo[0]);
    }

    public function testMethodNotAllowed(): void
    {
        Router::get('/tests/method-not-allowed', function () {
            return 'test';
        });

        $routeInfo = $this->dispatch('POST', '/tests/method-not-allowed');
        
        $this->assertEquals(Dispatcher::METHOD_NOT_ALLOWED, $routeInfo[0]);
    }
}


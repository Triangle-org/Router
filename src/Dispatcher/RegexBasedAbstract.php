<?php declare(strict_types = 1);

/**
 * @package     Triangle Router Component
 * @link        https://github.com/Triangle-org/Router
 *
 * @author      Ivan Zorin <creator@localzet.com>
 * @copyright   Copyright (c) 2023-2025 Triangle Framework Team
 * @license     https://www.gnu.org/licenses/agpl-3.0 GNU Affero General Public License v3.0
 *
 *              This program is free software: you can redistribute it and/or modify
 *              it under the terms of the GNU Affero General Public License as published
 *              by the Free Software Foundation, either version 3 of the License, or
 *              (at your option) any later version.
 *              
 *              This program is distributed in the hope that it will be useful,
 *              but WITHOUT ANY WARRANTY; without even the implied warranty of
 *              MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *              GNU Affero General Public License for more details.
 *              
 *              You should have received a copy of the GNU Affero General Public License
 *              along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *              
 *              For any questions, please contact <triangle@localzet.com>
 */

namespace Triangle\Router\Dispatcher;

use Triangle\Router\Dispatcher;
use Triangle\Router\RouteStatic;

abstract class RegexBasedAbstract implements Dispatcher
{
    /** @var mixed[][] */
    protected $staticRouteMap = [];

    /** @var mixed[] */
    protected $variableRouteData = [];

    /**
     * @return mixed[]
     */
    abstract protected function dispatchVariableRoute($routeData, $uri);

    public function dispatch($httpMethod, $uri)
    {
        if (isset($this->staticRouteMap[$httpMethod][$uri])) {
            /** @var RouteStatic $route */
            $route = $this->staticRouteMap[$httpMethod][$uri];
            return [self::FOUND, $route->callback, [], $route->object];
        }

        $varRouteData = $this->variableRouteData;
        if (isset($varRouteData[$httpMethod])) {
            $result = $this->dispatchVariableRoute($varRouteData[$httpMethod], $uri);
            if ($result[0] === self::FOUND) {
                return $result;
            }
        }

        // For HEAD requests, attempt fallback to GET
        if ($httpMethod === 'HEAD') {
            if (isset($this->staticRouteMap['GET'][$uri])) {
                /** @var RouteStatic $route */
                $route = $this->staticRouteMap['GET'][$uri];
                return [self::FOUND, $route->callback, [], $route->object];
            }
            if (isset($varRouteData['GET'])) {
                $result = $this->dispatchVariableRoute($varRouteData['GET'], $uri);
                if ($result[0] === self::FOUND) {
                    return $result;
                }
            }
        }

        // If nothing else matches, try fallback routes
        if (isset($this->staticRouteMap['*'][$uri])) {
            /** @var RouteStatic $route */
            $route = $this->staticRouteMap['*'][$uri];
            return [self::FOUND, $route->callback, [], $route->object];
        }
        if (isset($varRouteData['*'])) {
            $result = $this->dispatchVariableRoute($varRouteData['*'], $uri);
            if ($result[0] === self::FOUND) {
                return $result;
            }
        }

        // Find allowed methods for this URI by matching against all other HTTP methods as well
        $allowedMethods = [];

        foreach ($this->staticRouteMap as $method => $uriMap) {
            if ($method !== $httpMethod && isset($uriMap[$uri])) {
                $allowedMethods[] = $method;
            }
        }

        foreach ($varRouteData as $method => $routeData) {
            if ($method !== $httpMethod) {
                $result = $this->dispatchVariableRoute($routeData, $uri);
                if ($result[0] === self::FOUND) {
                    $allowedMethods[] = $method;
                }
            }
        }

        // If there are no allowed methods the route simply does not exist
        if ($allowedMethods) {
            return [self::METHOD_NOT_ALLOWED, $allowedMethods];
        }

        return [self::NOT_FOUND];
    }
}

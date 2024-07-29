<?php declare(strict_types = 1);

/**
 * @package     Triangle Router Component
 * @link        https://github.com/Triangle-org/Router
 *
 * @author      Ivan Zorin <creator@localzet.com>
 * @copyright   Copyright (c) 2023-2024 Triangle Framework Team
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

namespace Triangle\Router;

class RouteCollector
{
    /** @var RouteParser */
    protected $routeParser;

    /** @var DataGenerator */
    protected $dataGenerator;

    /** @var string */
    protected $currentGroupPrefix;

    /**
     * Constructs a route collector.
     *
     * @param RouteParser $routeParser
     * @param DataGenerator $dataGenerator
     */
    public function __construct(RouteParser $routeParser, DataGenerator $dataGenerator)
    {
        $this->routeParser = $routeParser;
        $this->dataGenerator = $dataGenerator;
        $this->currentGroupPrefix = '';
    }

    /**
     * Adds a route to the collection.
     *
     * The syntax used in the $route string depends on the used route parser.
     *
     * @param string|string[] $httpMethod
     * @param string $path
     * @param callable|mixed $callback
     */
    public function addRoute(array|string $httpMethod, string $path, mixed $callback)
    {
        $path = $this->currentGroupPrefix . $path;
        if (is_string($callback) && strpos($callback, '@')) {
            $callback = explode('@', $callback, 2);
        }

        if (!is_array($callback)) {
            if (!is_callable($callback)) {
                $callStr = is_scalar($callback) ? $callback : 'Closure';
                throw new BadRouteException("Router $path $callStr is not callable\n");
            }
        } else {
            $callback = array_values($callback);
            if (!isset($callback[1]) || !class_exists($callback[0]) || !method_exists($callback[0], $callback[1])) {
                throw new BadRouteException("Router $path " . json_encode($callback) . " is not callable\n");
            }
        }

        $object = new RouteObject($httpMethod, $path, $callback);
        $routeDatas = $this->routeParser->parse($path);
        foreach ((array)$httpMethod as $method) {
            foreach ($routeDatas as $data) {
                $this->dataGenerator->addRoute($method, $path, $data, $callback, $object);
            }
        }
        return $object;
    }

    /**
     * Create a route group with a common prefix.
     *
     * All routes created in the past callback will have the given group prefix prepended.
     *
     */
    public function addGroup(string $path, callable $callback)
    {
        $previousGroupPrefix = $this->currentGroupPrefix;
        $this->currentGroupPrefix = $previousGroupPrefix . $path;
        $callback($this);
        $this->currentGroupPrefix = $previousGroupPrefix;
    }

    /**
     * Adds a GET route to the collection
     *
     * This is simply an alias of $this->addRoute('GET', $path, $callback)
     *
     * @param string $path
     * @param mixed $callback
     */
    public function get(string $path, $callback)
    {
        return $this->addRoute('GET', $path, $callback);
    }

    /**
     * Adds a POST route to the collection
     *
     * This is simply an alias of $this->addRoute('POST', $path, $callback)
     *
     * @param string $path
     * @param mixed $callback
     */
    public function post(string $path, $callback)
    {
        return $this->addRoute('POST', $path, $callback);
    }

    /**
     * Adds a PUT route to the collection
     *
     * This is simply an alias of $this->addRoute('PUT', $path, $callback)
     *
     * @param string $path
     * @param mixed $callback
     */
    public function put(string $path, $callback)
    {
        return $this->addRoute('PUT', $path, $callback);
    }

    /**
     * Adds a DELETE route to the collection
     *
     * This is simply an alias of $this->addRoute('DELETE', $path, $callback)
     *
     * @param string $path
     * @param mixed $callback
     */
    public function delete(string $path, $callback)
    {
        return $this->addRoute('DELETE', $path, $callback);
    }

    /**
     * Adds a PATCH route to the collection
     *
     * This is simply an alias of $this->addRoute('PATCH', $path, $callback)
     *
     * @param string $path
     * @param mixed $callback
     */
    public function patch(string $path, $callback)
    {
        return $this->addRoute('PATCH', $path, $callback);
    }

    /**
     * Adds a HEAD route to the collection
     *
     * This is simply an alias of $this->addRoute('HEAD', $path, $callback)
     *
     * @param string $path
     * @param mixed $callback
     */
    public function head(string $path, $callback)
    {
        return $this->addRoute('HEAD', $path, $callback);
    }

    /**
     * Adds a OPTIONS route to the collection
     *
     * This is simply an alias of $this->addRoute('OPTIONS', $path, $callback)
     *
     * @param string $path
     * @param mixed $callback
     */
    public function options(string $path, mixed $callback)
    {
        return $this->addRoute('OPTIONS', $path, $callback);
    }

    public function any(string $path, mixed $callback)
    {
        return $this->addRoute('*', $path, $callback);
    }

    /**
     * Returns the collected route data, as provided by the data generator.
     *
     * @return array
     */
    public function getData()
    {
        return $this->dataGenerator->getData();
    }

    public function getRoutes()
    {
        return $this->dataGenerator->getRoutes();
    }
}

<?php declare(strict_types=1);

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

use Triangle\Router;
use Triangle\Router\DataGenerator;
use Triangle\Router\Dispatcher;
use Triangle\Router\RouteParser;

if (!function_exists('simpleRouteDispatcher')) {
    /**
     * @param callable $routeDefinitionCallback
     * @param array $options
     *
     * @return Dispatcher
     */
    function simpleRouteDispatcher(callable $routeDefinitionCallback, array $options = []): Dispatcher
    {
        $options += [
            'routeParser' => RouteParser\Std::class,
            'dataGenerator' => DataGenerator\GroupCountBased::class,
            'dispatcher' => Dispatcher\GroupCountBased::class,
        ];

        $router = new Router(
            new $options['routeParser'], new $options['dataGenerator']
        );
        $routeDefinitionCallback($router);

        return new $options['dispatcher']($router->getData());
    }
}

if (!function_exists('cachedRouteDispatcher')) {
    /**
     * @param callable $routeDefinitionCallback
     * @param array $options
     *
     * @return Dispatcher
     */
    function cachedRouteDispatcher(callable $routeDefinitionCallback, array $options = []): Dispatcher
    {
        $options += [
            'routeParser' => 'Triangle\\Router\\RouteParser\\Std',
            'dataGenerator' => 'Triangle\\Router\\DataGenerator\\GroupCountBased',
            'dispatcher' => 'Triangle\\Router\\Dispatcher\\GroupCountBased',
            'cacheDisabled' => false,
        ];

        if (!isset($options['cacheFile'])) {
            throw new LogicException('Must specify "cacheFile" option');
        }

        if (!$options['cacheDisabled'] && file_exists($options['cacheFile'])) {
            $dispatchData = require $options['cacheFile'];
            if (!is_array($dispatchData)) {
                throw new RuntimeException('Invalid cache file "' . $options['cacheFile'] . '"');
            }
            return new $options['dispatcher']($dispatchData);
        }

        $router = new Router(
            new $options['routeParser'], new $options['dataGenerator']
        );
        $routeDefinitionCallback($router);

        $dispatchData = $router->getData();
        if (!$options['cacheDisabled']) {
            file_put_contents(
                $options['cacheFile'],
                '<?php return ' . var_export($dispatchData, true) . ';'
            );
        }

        return new $options['dispatcher']($dispatchData);
    }
}

if (!function_exists('route')) {
    /**
     * @param string $name
     * @param ...$parameters
     * @return string
     */
    function route(string $name, ...$parameters): string
    {
        $route = Router::getByName($name);
        if (!$route) {
            return '';
        }

        if (!$parameters) {
            return $route->url();
        }

        if (is_array(current($parameters))) {
            $parameters = current($parameters);
        }

        return $route->url($parameters);
    }
}
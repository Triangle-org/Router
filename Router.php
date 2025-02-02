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

namespace Triangle;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionAttribute;
use ReflectionClass;
use Triangle\Annotation\DisableDefaultRoute;
use Triangle\Http\App;
use Triangle\Router\BadRouteException;
use Triangle\Router\DataGenerator;
use Triangle\Router\Dispatcher;
use Triangle\Router\RouteObject;
use Triangle\Router\RouteParser;

class Router
{
    protected static ?DataGenerator $dataGenerator = null;

    protected ?Dispatcher $dispatcher = null;
    protected static ?RouteParser $routeParser = null;
    protected array $children = [];
    protected array $routes = [];
    protected string $currentGroupPrefix = '';
    protected static ?Router $instance = null;

    protected static array $disableDefaultRoute = [];
    protected static array $nameList = [];
    protected static array $fallback = [];

    protected static array $fallbackRoutes = [];
    protected static array $disabledDefaultRoutes = [];
    protected static array $disabledDefaultRouteControllers = [];
    protected static array $disabledDefaultRouteActions = [];

    /**
     * Adds a route to the collection.
     *
     * The syntax used in the $route string depends on the used route parser.
     *
     * @param string|string[] $httpMethod
     * @param string $path
     * @param callable|mixed $callback
     * @return RouteObject
     */
    public function addRoute(array|string $httpMethod, string $path, mixed $callback): RouteObject
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
        $routeDatas = static::routeParser()->parse($path);
        foreach ((array)$httpMethod as $method) {
            foreach ($routeDatas as $data) {
                static::dataGenerator()->addRoute($method, $path, $data, $callback, $object);
            }
        }
        $this->routes[] = $object;
        return $object;
    }

    public static function route(array|string $methods, string $path, mixed $callback): RouteObject
    {
        return static::instance()?->addRoute($methods, $path, $callback);
    }

    public function addGroup(string $path, callable $callback): static
    {
        $previousGroupPrefix = $this->currentGroupPrefix;
        $this->currentGroupPrefix = $previousGroupPrefix . $path;

        $callback($this);

        $this->currentGroupPrefix = $previousGroupPrefix;
        return $this;
    }

    public static function group(string $path, callable $callback): static
    {
        $prevInstance = static::$instance;
        $nextInstance = static::$instance = new static;

        static::$instance?->addGroup(($prevInstance?->currentGroupPrefix ?? '') . $path, $callback);

        $prevInstance?->addChild($nextInstance);
        static::$instance = $prevInstance;

        return $nextInstance;
    }

    public function addChild(Router $route): void
    {
        $this->children[] = $route;
    }

    public static function child(string $path, callable $callback = null): static
    {
        $prevInstance = static::$instance;
        $nextInstance = static::$instance = new static;

        $callback(static::$instance);

        $prevInstance?->addChild($nextInstance);
        static::$instance = $prevInstance;

        return $nextInstance;
    }

    public function getData()
    {
        return static::dataGenerator()->getData();
    }

    public static function data(): array
    {
        return static::instance()?->getData();
    }

    public function getRoutes()
    {
        return static::dataGenerator()->getRoutes();
    }

    public static function routes(): array
    {
        return static::instance()?->getRoutes();
    }


    public static function disableDefaultRoute(?string $plugin = '', string $app = null): bool
    {
        // Is [controller action]
        if (is_array($plugin)) {
            $controllerAction = $plugin;
            if (!isset($controllerAction[0]) || !is_string($controllerAction[0]) ||
                !isset($controllerAction[1]) || !is_string($controllerAction[1])) {
                return false;
            }
            $controller = $controllerAction[0];
            $action = $controllerAction[1];
            static::$disabledDefaultRouteActions[$controller][$action] = $action;
            return true;
        }
        // Is plugin
        if (is_string($plugin) && (preg_match('/^[a-zA-Z0-9_]+$/', $plugin) || $plugin === '')) {
            if (!isset(static::$disabledDefaultRoutes[$plugin])) {
                static::$disabledDefaultRoutes[$plugin] = [];
            }
            $app = $app ?? '*';
            static::$disabledDefaultRoutes[$plugin][$app] = $app;
            return true;
        }
        // Is controller
        if (is_string($plugin) && class_exists($plugin)) {
            static::$disabledDefaultRouteControllers[$plugin] = $plugin;
            return true;
        }
        return false;
    }

    public static function isDefaultRouteDisabled($plugin = '', string $app = null): bool
    {
        // Is [controller action]
        if (is_array($plugin)) {
            if (!isset($plugin[0]) || !is_string($plugin[0]) ||
                !isset($plugin[1]) || !is_string($plugin[1])) {
                return false;
            }
            return isset(static::$disabledDefaultRouteActions[$plugin[0]][$plugin[1]]) || static::isDefaultRouteDisabledByAnnotation($plugin[0], $plugin[1]);
        }
        // Is plugin
        if (is_string($plugin) && (preg_match('/^[a-zA-Z0-9_]+$/', $plugin) || $plugin === '')) {
            $app = $app ?? '*';
            return isset(static::$disabledDefaultRoutes[$plugin]['*']) || isset(static::$disabledDefaultRoutes[$plugin][$app]);
        }
        // Is controller
        if (is_string($plugin) && class_exists($plugin)) {
            return isset(static::$disabledDefaultRouteControllers[$plugin]);
        }
        return false;
    }

    /**
     * @param string $controller
     * @param string|null $action
     * @return bool
     */
    protected static function isDefaultRouteDisabledByAnnotation(string $controller, ?string $action = null): bool
    {
        if (class_exists($controller)) {
            $reflectionClass = new ReflectionClass($controller);
            if ($reflectionClass->getAttributes(DisableDefaultRoute::class, ReflectionAttribute::IS_INSTANCEOF)) {
                return true;
            }
            if ($action && $reflectionClass->hasMethod($action)) {
                $reflectionMethod = $reflectionClass->getMethod($action);
                if ($reflectionMethod->getAttributes(DisableDefaultRoute::class, ReflectionAttribute::IS_INSTANCEOF)) {
                    return true;
                }
            }
        }
        return false;
    }

    public static function setByName(string $name, RouteObject $instance): void
    {
        static::$nameList[$name] = $instance;
    }

    public static function getByName(string $name): ?RouteObject
    {
        return static::$nameList[$name] ?? null;
    }


    public static function fallback(callable $callback, ?string $plugin = ''): RouteObject
    {
        $route = new RouteObject([], '', $callback);
        static::$fallbackRoutes[$plugin ?? ''] = $route;
        return $route;
    }

    public static function getFallback(?string $plugin = '', int $status = 404): ?callable
    {
        if (!isset(static::$fallback[$plugin])) {
            $route = static::$fallbackRoutes[$plugin] ?? null;
            static::$fallback[$plugin] = $route ? App::getCallback($plugin, 'NOT_FOUND', $route->getCallback(), ['status' => $status], false, $route) : null;
        }
        return static::$fallback[$plugin];
    }


    public static function get(string $path, mixed $callback): RouteObject
    {
        return static::instance()->addRoute('GET', $path, $callback);
    }

    public static function post(string $path, mixed $callback): RouteObject
    {
        return static::instance()->addRoute('POST', $path, $callback);
    }

    public static function put(string $path, mixed $callback): RouteObject
    {
        return static::instance()->addRoute('PUT', $path, $callback);
    }

    public static function patch(string $path, mixed $callback): RouteObject
    {
        return static::instance()->addRoute('PATCH', $path, $callback);
    }

    public static function delete(string $path, mixed $callback): RouteObject
    {
        return static::instance()->addRoute('DELETE', $path, $callback);
    }

    public static function head(string $path, mixed $callback): RouteObject
    {
        return static::instance()->addRoute('HEAD', $path, $callback);
    }

    public static function options(string $path, mixed $callback): RouteObject
    {
        return static::instance()->addRoute('OPTIONS', $path, $callback);
    }

    public static function any(string $path, mixed $callback): RouteObject
    {
        return static::instance()->addRoute('*', $path, $callback);
    }

    public static function resource(string $name, string $controller, array $actions = null, string $prefix = ''): void
    {
        $name = trim($name, '/');
        $selected = !empty($actions);

        if ($selected) {
            $diffOptions = array_diff($actions, ['index', 'create', 'store', 'update', 'show', 'edit', 'destroy', 'patch']);
            if (!empty($diffOptions)) {
                foreach ($diffOptions as $action) {
                    static::any("/$name/{$action}[/{id}]", [$controller, $action])->name("$prefix$name.$action");
                }
            }
        }

        // Отображение списка ресурсов
        if (
            ($selected && in_array('index', $actions))
            || (!$selected && method_exists($controller, 'index'))
        ) static::get("/$name", [$controller, 'index'])->name("$prefix$name.index");

        // Создание нового ресурса
        if (
            ($selected && in_array('store', $actions))
            || (!$selected && method_exists($controller, 'store'))
        ) static::post("/$name", [$controller, 'store'])->name("$prefix$name.store");

        // Отображение формы для создания нового ресурса
        if (
            ($selected && in_array('create', $actions))
            || (!$selected && method_exists($controller, 'create'))
        ) static::get("/$name/create", [$controller, 'create'])->name("$prefix$name.create");

        // Отображение формы для обновления существующего ресурса
        if (
            ($selected && in_array('edit', $actions))
            || (!$selected && method_exists($controller, 'edit'))
        ) static::get("/$name/{id}/edit", [$controller, 'edit'])->name("$prefix$name.edit");

        // Отображение конкретного ресурса
        if (
            ($selected && in_array('show', $actions))
            || (!$selected && method_exists($controller, 'show'))
        ) static::get("/$name/{id}", [$controller, 'show'])->name("$prefix$name.show");

        // Обновление существующего ресурса
        if (
            ($selected && in_array('update', $actions))
            || (!$selected && method_exists($controller, 'update'))
        ) static::put("/$name/{id}", [$controller, 'update'])->name("$prefix$name.update");

        // Изменение существующего ресурса
        if (
            ($selected && in_array('patch', $actions))
            || (!$selected && method_exists($controller, 'patch'))
        ) static::patch("/$name/{id}", [$controller, 'patch'])->name("$name.patch");

        // Удаление существующего ресурса
        if (
            ($selected && in_array('destroy', $actions))
            || (!$selected && method_exists($controller, 'destroy'))
        ) static::delete("/$name/{id}", [$controller, 'destroy'])->name("$prefix$name.destroy");
    }


    public static function dispatch(string $method, string $path): array
    {
        return static::instance()->dispatcher->dispatch($method, $path);
    }

    public static function routeParser()
    {
        return static::$routeParser ??= new Router\RouteParser\Std();
    }

    public static function dataGenerator()
    {
        return static::$dataGenerator ??= new Router\DataGenerator\GroupCountBased();
    }

    public static function instance()
    {
        return static::$instance ??= new static;
    }

    public static function collect(array $paths): void
    {

        foreach ($paths as $configPath) {
            $routeConfigFile = $configPath . '/route.php';
            if (is_file($routeConfigFile)) {
                require_once $routeConfigFile;
            }
            if (!is_dir($pluginConfigPath = $configPath . '/plugin')) {
                continue;
            }
            $dirIterator = new RecursiveDirectoryIterator($pluginConfigPath, FilesystemIterator::FOLLOW_SYMLINKS);
            $iterator = new RecursiveIteratorIterator($dirIterator);
            foreach ($iterator as $file) {
                if ($file->getBaseName('.php') !== 'route') {
                    continue;
                }
                $appConfigFile = pathinfo($file, PATHINFO_DIRNAME) . '/app.php';
                if (!is_file($appConfigFile)) {
                    continue;
                }
                $appConfig = include $appConfigFile;
                if (empty($appConfig['enable'])) {
                    continue;
                }
                require_once $file;
            }
        }

        static::instance()->dispatcher = new Router\Dispatcher\GroupCountBased(
            static::dataGenerator()->getData()
        );
    }


    public function middleware($middleware): Router
    {
        foreach ($this->routes as $route) {
            $route->middleware($middleware);
        }
        foreach ($this->children as $child) {
            $child->middleware($middleware);
        }
        return $this;
    }
}
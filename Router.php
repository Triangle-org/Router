<?php declare(strict_types=1);

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

namespace Triangle;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Triangle\Router\RouteObject;

class Router
{
    protected static ?Router\RouteCollector $collector = null;

    protected static ?Router\Dispatcher $dispatcher = null;

    protected static ?Router $instance = null;

    protected static array $disableDefaultRoute = [];

    protected static array $nameList = [];

    protected static array $fallback = [];

    protected array $routes = [];

    protected array $children = [];


    public static function route(array|string $methods, string $path, mixed $callback): RouteObject
    {
        $object = static::$collector->addRoute($methods, $path, $callback);
        static::$instance?->addRoute($object);
        return $object;
    }

    public static function group(string $path, callable $callback = null): static
    {
        $prevInstance = static::$instance;
        $nextInstance = static::$instance = new static;

        static::$collector->addGroup($path, $callback);

        $prevInstance?->addChild($nextInstance);
        static::$instance = $prevInstance;

        return $nextInstance;
    }

    public static function child(string $path, callable $callback = null): static
    {
        $prevInstance = static::$instance;
        $nextInstance = static::$instance = new static;

        $callback(static::$collector);

        $prevInstance?->addChild($nextInstance);
        static::$instance = $prevInstance;

        return $nextInstance;
    }

    public static function getRoutes(): array
    {
        return static::$collector->getRoutes();
    }


    public static function disableDefaultRoute(?string $plugin = ''): void
    {
        static::$disableDefaultRoute[$plugin ?? ''] = true;
    }

    public static function hasDisableDefaultRoute(?string $plugin = ''): bool
    {
        return static::$disableDefaultRoute[$plugin ?? ''] ?? false;
    }


    public static function setByName(string $name, RouteObject $instance): void
    {
        static::$nameList[$name] = $instance;
    }

    public static function getByName(string $name): ?RouteObject
    {
        return static::$nameList[$name] ?? null;
    }


    public static function fallback(callable $callback, ?string $plugin = ''): void
    {
        static::$fallback[$plugin ?? ''] = $callback;
    }

    public static function getFallback(?string $plugin = ''): ?callable
    {
        return static::$fallback[$plugin ?? ''] ?? null;
    }


    public static function get(string $path, mixed $callback): RouteObject
    {
        return static::$collector->get($path, $callback);
    }

    public static function post(string $path, mixed $callback): RouteObject
    {
        return static::$collector->post($path, $callback);
    }

    public static function put(string $path, mixed $callback): RouteObject
    {
        return static::$collector->put($path, $callback);
    }

    public static function patch(string $path, mixed $callback): RouteObject
    {
        return static::$collector->patch($path, $callback);
    }

    public static function delete(string $path, mixed $callback): RouteObject
    {
        return static::$collector->delete($path, $callback);
    }

    public static function head(string $path, mixed $callback): RouteObject
    {
        return static::$collector->head($path, $callback);
    }

    public static function options(string $path, mixed $callback): RouteObject
    {
        return static::$collector->options($path, $callback);
    }

    public static function any(string $path, mixed $callback): RouteObject
    {
        return static::$collector->any($path, $callback);
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
        return static::$dispatcher->dispatch($method, $path);
    }

    public static function collect(array $paths): void
    {
        static::$collector ??= new Router\RouteCollector(
            new Router\RouteParser\Std(),
            new Router\DataGenerator\GroupCountBased()
        );

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

        static::$dispatcher = new Router\Dispatcher\GroupCountBased(
            static::$collector->getData()
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

    public function addChild(Router $route): void
    {
        $this->children[] = $route;
    }

    public function addRoute(RouteObject $route): void
    {
        $this->routes[] = $route;
    }
}
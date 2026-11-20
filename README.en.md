<p align="center"><a href="https://www.localzet.com" target="_blank">
  <img src="https://static.zorin.space/media/logos/LocalzetGroup.png" width="400" alt="Localzet Group">
</a></p>

# Triangle Router

[![Packagist version](https://img.shields.io/packagist/v/triangle/router)](https://packagist.org/packages/triangle/router)
[![PHP requirement](https://img.shields.io/packagist/dependency-v/triangle/router/php)](https://packagist.org/packages/triangle/router)
[![License](https://img.shields.io/github/license/Triangle-org/Router)](https://github.com/Triangle-org/Router)

Triangle Router matches HTTP methods and URI paths against route patterns and returns the matched handler with named parameters. It is a routing component; the application remains responsible for invoking handlers and producing HTTP responses.

[Русская документация](README.md)

## Requirements and compatibility

- PHP 8.1 or later (`^8.1` in Composer).
- `triangle/engine` `^8.1 || ^9.0` in the current source branch.

Engine 9 does not yet have a stable release tag. The source constraint is not proof that a stable published Composer release is available or verified against Engine 9. Check the resolved packages with `composer show triangle/router triangle/engine`.

## Installation

```sh
composer require triangle/router
```

Composer loads the package functions from `src/functions.php`, so the Composer autoloader must be included before calling `simpleRouteDispatcher()` or `cachedRouteDispatcher()`.

## Quick start

```php
<?php

require __DIR__ . '/vendor/autoload.php';

$dispatcher = simpleRouteDispatcher(static function (Triangle\Router $router): void {
    $router->addRoute('GET', '/users/{id:\\d+}', static fn (array $params): array => $params);
});

$result = $dispatcher->dispatch('GET', '/users/42');

switch ($result[0]) {
    case Triangle\Router\Dispatcher::FOUND:
        [$status, $handler, $parameters] = $result;
        // Invoke the handler and translate its result to an HTTP response.
        break;

    case Triangle\Router\Dispatcher::METHOD_NOT_ALLOWED:
        $allowedMethods = $result[1];
        // Return 405 and include the methods in the Allow response header.
        break;

    case Triangle\Router\Dispatcher::NOT_FOUND:
        // Return 404.
        break;
}
```

`FOUND` results contain the handler and an associative array of route parameters. `METHOD_NOT_ALLOWED` results contain the allowed HTTP methods in the second tuple item. The router does not create the HTTP response or set its headers.

## Defining routes

`simpleRouteDispatcher()` accepts a callback that receives a `Triangle\Router` instance. Register one or several methods:

```php
$router->addRoute('GET', '/health', static fn (): string => 'ok');
$router->addRoute(['GET', 'POST'], '/users', static fn (): array => []);
$router->addRoute('GET', '/users/{id:\\d+}[/{tab}]', static fn (array $params): array => $params);
```

A `{name}` placeholder matches a non-empty path segment by default. Add a regular expression after the colon to constrain it, such as `{id:\\d+}`. Bracketed sections are optional route parts. Custom expressions should not capture groups that alter parameter matching.

Route groups can prefix multiple routes. The package also provides the `get`, `post`, `put`, `patch`, `delete`, `head`, `options`, and `any` helper methods through `Triangle\Router`; see the class API for their exact signatures.

## Route cache

For applications that want to persist the generated dispatch data, use `cachedRouteDispatcher()` and supply a cache file:

```php
$dispatcher = cachedRouteDispatcher($defineRoutes, [
    'cacheFile' => __DIR__ . '/var/routes.php',
]);
```

The helper loads an existing PHP cache file unless `cacheDisabled` is enabled; otherwise it builds the route data and writes it to the configured path. Ensure the deployment process can write that path when generating the cache, and regenerate it whenever route definitions change. Do not use a writable cache directory as a trust boundary: the generated file is executable PHP.

## HTTP method behavior

The dispatcher reports `FOUND`, `NOT_FOUND`, or `METHOD_NOT_ALLOWED`. Applications should turn these into appropriate responses and include an `Allow` header for a 405 response using the methods returned by the dispatcher.

A GET route can also match HEAD when no explicit HEAD route exists. PHP SAPI normally suppresses the response body for HEAD. Applications running outside PHP SAPI must suppress it themselves; the router does not control response transmission.

## Triangle Engine integration

The package includes `Triangle\Router\Bootstrap`, which implements the Engine bootstrap interface. When started with a `localzet\Server` instance it collects route configuration from the configured application paths and plugin configuration directories. For framework applications, follow the Engine bootstrap and route-configuration conventions of the Engine version actually installed. The standalone dispatcher examples above do not require the Engine request lifecycle.

## Tests

Run the repository's declared test suite from its root:

```sh
composer test --no-interaction
```

This runs PHPUnit using `tests/phpunit.xml`. The current unit suite contains four tests in `tests/Unit/RouteTest.php`; it is not an exhaustive integration or production compatibility suite.

## License

AGPL-3.0-or-later. See [LICENSE](LICENSE).

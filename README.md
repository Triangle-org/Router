<p align="center"><a href="https://www.localzet.com" target="_blank">
  <img src="https://cdn.localzet.com/assets/media/logos/ZorinProjectsSP.svg" width="400">
</a></p>

<p align="center">
  <a href="https://packagist.org/packages/triangle/router">
  <img src="https://img.shields.io/packagist/dt/triangle/router?label=%D0%A1%D0%BA%D0%B0%D1%87%D0%B8%D0%B2%D0%B0%D0%BD%D0%B8%D1%8F" alt="Скачивания">
</a>
  <a href="https://github.com/Triangle-org/Router">
  <img src="https://img.shields.io/github/commit-activity/t/Triangle-org/Router?label=%D0%9A%D0%BE%D0%BC%D0%BC%D0%B8%D1%82%D1%8B" alt="Коммиты">
</a>
  <a href="https://packagist.org/packages/triangle/router">
  <img src="https://img.shields.io/packagist/v/triangle/router?label=%D0%92%D0%B5%D1%80%D1%81%D0%B8%D1%8F" alt="Версия">
</a>
  <a href="https://packagist.org/packages/triangle/router">
  <img src="https://img.shields.io/packagist/dependency-v/triangle/router/php?label=PHP" alt="Версия PHP">
</a>
  <a href="https://github.com/Triangle-org/Router">
  <img src="https://img.shields.io/github/license/Triangle-org/Router?label=%D0%9B%D0%B8%D1%86%D0%B5%D0%BD%D0%B7%D0%B8%D1%8F" alt="Лицензия">
</a>
</p>

Маршрутизатор HTTP-запросов
=======================================

Эта библиотека обеспечивает быструю реализацию маршрутизатора на основе регулярных выражений.
[Статья, о том, как работает реализация и почему она быстрее.][blog_post]

Установка
-------

Для установки через Composer:

```sh
composer require triangle/router
```

Требуется PHP 8.0 или выше.

Usage
-----

Пример использования:

```php
<?php

require '/path/to/vendor/autoload.php';

$dispatcher = simpleRouteDispatcher(function(Triangle\Router\RouteCollector $r) {
    $r->addRoute('GET', '/users', 'get_all_users_handler');
    // {id} должен быть числом (\d+)
    $r->addRoute('GET', '/user/{id:\d+}', 'get_user_handler');
    // Суффикс /{title} не обязателен
    $r->addRoute('GET', '/articles/{id:\d+}[/{title}]', 'get_article_handler');
});

// Получаем метод и URI откуда нужно
$httpMethod = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

// Удалим строку запроса (?foo=bar) и декодируем URI
if (false !== $pos = strpos($uri, '?')) {
    $uri = substr($uri, 0, $pos);
}
$uri = rawurldecode($uri);

$routeInfo = $dispatcher->dispatch($httpMethod, $uri);
switch ($routeInfo[0]) {
    case Triangle\Router\Dispatcher::NOT_FOUND:
        // ... 404 Ничего не найдено
        break;
    case Triangle\Router\Dispatcher::METHOD_NOT_ALLOWED:
        $allowedMethods = $routeInfo[1];
        // ... 405 Метод не поддерживается
        break;
    case Triangle\Router\Dispatcher::FOUND:
        $callback = $routeInfo[1];
        $vars = $routeInfo[2];
        // ... Здесь можно вызвать, например $callback($vars)
        break;
}
```

### Определение маршрутов

Маршруты определяются путем вызова функции `simpleRouteDispatcher()`, которая принимает
вызываемый объект, принимающий экземпляр `Triangle\Router\RouteCollector`. Маршруты добавляются путем вызова
`addRoute()` на экземпляре коллектора:

```php
$r->addRoute($method, $routePattern, $handler);
```

Параметр `$method` — это строка HTTP-метода в верхнем регистре, для которой должен совпадать определенный маршрут.
Можно указать несколько допустимых методов с помощью массива:

```php
// 2 маршрута:
$r->addRoute('GET', '/test', 'handler');
$r->addRoute('POST', '/test', 'handler');
// Эквивалентны записи:
$r->addRoute(['GET', 'POST'], '/test', 'handler');
```

По умолчанию `$routePattern` использует синтаксис, где `{foo}` указывает заполнитель с именем `foo`
и соответствует регулярному выражению `[^/]+`. Чтобы настроить шаблон, которому соответствует заполнитель, вы можете указать
пользовательский шаблон, написав `{bar:[0-9]+}`. Вот несколько примеров:

```php
// Маршрут /user/42 совпадёт, но /user/xyz - уже нет
$r->addRoute('GET', '/user/{id:\d+}', 'handler');

// Маршрут /user/foobar совпадёт, но /user/foo/bar  - уже нет
$r->addRoute('GET', '/user/{name}', 'handler');

// Маршрут /user/foo/bar совпадёт
$r->addRoute('GET', '/user/{name:.+}', 'handler');
```

Пользовательские шаблоны для заполнителей маршрутов не могут использовать группы захвата. Например, `{lang:(en|de)}`
не является допустимым заполнителем, поскольку `()` является группой захвата. Вместо этого вы можете использовать либо `{lang:en|de}`, либо `{lang:(?:en|de)}`.

Кроме того, части маршрута, заключенные в `[...]`, считаются необязательными, поэтому `/foo[bar]`
будет соответствовать как `/foo`, так и `/foobar`. Необязательные части поддерживаются только в конце,
но не в середине маршрута.

```php
// Этот маршрут
$r->addRoute('GET', '/user/{id:\d+}[/{name}]', 'handler');
// Эквивалентен двум следующим
$r->addRoute('GET', '/user/{id:\d+}', 'handler');
$r->addRoute('GET', '/user/{id:\d+}/{name}', 'handler');

// Также возможны множественные вложенные необязательные части
$r->addRoute('GET', '/user[/{id:\d+}[/{name}]]', 'handler');

// Этот маршрут НЕ действителен, поскольку необязательные части могут встречаться только в конце
$r->addRoute('GET', '/user[/{id:\d+}]/{name}', 'handler');
```

Параметр `$handler` не обязательно должен быть обратным вызовом, он также может быть именем класса контроллера
или любым другим типом данных, которые вы хотите связать с маршрутом. Маршрутизатор только сообщает вам,
какой обработчик соответствует вашему URI, как вы его интерпретируете, зависит от вас.

#### Сокращения для популярных методов

Для методов `GET`, `POST`, `PUT`, `PATCH`, `DELETE`, `HEAD` и `OPTIONS` доступны сокращения. Например, маршруты:

```php
$r->get('/get-route', 'get_handler');
$r->post('/post-route', 'post_handler');
```

Эквивалентны:

```php
$r->addRoute('GET', '/get-route', 'get_handler');
$r->addRoute('POST', '/post-route', 'post_handler');
```

Если маршрут предполагает все существующие (и не существующие) методы - можно использовать:

```php
$r->any('/any-route', 'any_handler');
```

#### Группы маршрутов

Кроме того, вы можете указать маршруты внутри группы. Все маршруты, определенные внутри группы, будут иметь общий префикс.

Например, определение ваших маршрутов как:

```php
$r->addGroup('/admin', function (RouteCollector $r) {
    $r->addRoute('GET', '/do-something', 'handler');
    $r->addRoute('GET', '/do-another-thing', 'handler');
    $r->addRoute('GET', '/do-something-else', 'handler');
});
```

Будет иметь тот же результат, что и:

 ```php
$r->addRoute('GET', '/admin/do-something', 'handler');
$r->addRoute('GET', '/admin/do-another-thing', 'handler');
$r->addRoute('GET', '/admin/do-something-else', 'handler');
 ```

Также поддерживаются вложенные группы, в этом случае префиксы всех вложенных групп объединяются.

### Кэширование

Причина, по которой `simpleRouteDispatcher` принимает обратный вызов для определения маршрутов, заключается в том, чтобы разрешить бесшовное
кэширование. Используя `cachedRouteDispatcher` вместо `simpleRouteDispatcher`, вы можете кэшировать сгенерированные
данные маршрутизации и создать диспетчер из кэшированной информации:

```php
<?php

$dispatcher = cachedRouteDispatcher(function(Triangle\Router\RouteCollector $r) {
    $r->addRoute('GET', '/user/{name}/{id:[0-9]+}', 'handler0');
    $r->addRoute('GET', '/user/{id:[0-9]+}', 'handler1');
    $r->addRoute('GET', '/user/{name}', 'handler2');
}, [
    'cacheFile' => __DIR__ . '/route.cache', /* обязательно */
    'cacheDisabled' => IS_DEBUG_ENABLED,     /* необязательно, включено по умолчанию */
]);
```

Вторым параметром функции является массив параметров, который можно использовать, среди прочего, для указания местоположения файла кэша.

### Обработка URI

URI обрабатывается путем вызова метода `dispatch()` созданного диспетчера. Этот метод
принимает метод HTTP и URI. Получение этих двух битов информации (и их нормализация
соответствующим образом) — ваша работа — эта библиотека не привязана к SAPI PHP.

Метод `dispatch()` возвращает массив, первый элемент которого содержит код состояния. Это один из
`Dispatcher::NOT_FOUND`, `Dispatcher::METHOD_NOT_ALLOWED` и `Dispatcher::FOUND`. Для
состояния «метод не разрешен» второй элемент массива содержит список методов HTTP, разрешенных для
предоставленного URI. Например:

    [Triangle\Router\Dispatcher::METHOD_NOT_ALLOWED, ['GET', 'POST']]

> **ПРИМЕЧАНИЕ:** Спецификация HTTP требует, чтобы ответ `405 Method Not Allowed` включал заголовок `Allow:`
для детализации доступных методов для запрошенного ресурса. Приложения, использующие Triangle Router,
должны использовать второй элемент массива для добавления этого заголовка при ретрансляции ответа 405.

Для статуса `Dispatcher::FOUND` второй элемент массива — это обработчик, который был связан с маршрутом,
а третий элемент массива — это словарь имен переменных для их значений. Например:

    /* Маршрутизация по GET /user/localzet/42 */

    [Triangle\Router\Dispatcher::FOUND, 'handler0', ['name' => 'localzet', 'id' => '42']]

### Переопределение парсера маршрута и диспетчера

Процесс маршрутизации использует три компонента: парсер маршрута, генератор данных и
диспетчер. Три компонента придерживаются следующих интерфейсов:

```php
<?php

namespace Triangle\Router;

interface RouteParser {
    public function parse($route);
}

interface DataGenerator {
    public function addRoute($httpMethod, $routeData, $handler);
    public function getData();
}

interface Dispatcher {
    const NOT_FOUND = 0, FOUND = 1, METHOD_NOT_ALLOWED = 2;

    public function dispatch($httpMethod, $uri);
}
```

Парсер маршрута берет строку шаблона маршрута и преобразует ее в массив информаций о маршруте, где
каждая информация снова является массивом своих частей. Структуру лучше всего понять на примере:

    /* Маршрут /user/{id:\d+}[/{name}] преобразуется в следующий массив: */
    [
        [
            '/user/',
            ['id', '\d+'],
        ],
        [
            '/user/',
            ['id', '\d+'],
            '/',
            ['name', '[^/]+'],
        ],
    ]

Этот массив затем можно передать в метод `addRoute()` генератора данных. После добавления всех маршрутов
вызывается `getData()` генератора, который возвращает все данные маршрутизации, необходимые
диспетчеру. Формат этих данных далее не указывается — он тесно связан с
соответствующим диспетчером.

Диспетчер принимает данные маршрутизации через конструктор и предоставляет метод `dispatch()`, с которым
вы уже знакомы.

Парсер маршрута можно переопределить по отдельности (чтобы использовать другой синтаксис шаблона),
однако генератор данных и диспетчер всегда следует изменять как пару, поскольку вывод первого тесно связан со вводом
второго. Причина, по которой генератор и диспетчер разделены, заключается в том, что только последний
нужен при использовании кэширования (поскольку вывод первого — это то, что кэшируется.)

При использовании функций `simpleRouteDispatcher` / `cachedRouteDispatcher` из вышеприведенных
переопределение происходит через массив параметров:

```php
<?php

$dispatcher = simpleRouteDispatcher(function(Triangle\Router\RouteCollector $r) {
    /* ... */
}, [
    'routeParser' => 'Triangle\\Router\\RouteParser\\Std',
    'dataGenerator' => 'Triangle\\Router\\DataGenerator\\GroupCountBased',
    'dispatcher' => 'Triangle\\Router\\Dispatcher\\GroupCountBased',
]);
```

Массив параметров выше соответствует значениям по умолчанию. Заменив `GroupCountBased` на
`GroupPosBased`, вы можете переключиться на другую стратегию диспетчеризации.

### Примечание о запросах HEAD

Спецификация HTTP требует, чтобы серверы [поддерживали как методы GET, так и методы HEAD][2616-511]:

> Методы GET и HEAD ДОЛЖНЫ поддерживаться всеми серверами общего назначения

Чтобы не заставлять пользователей вручную регистрировать маршруты HEAD для каждого ресурса, мы возвращаемся к сопоставлению
доступного маршрута GET для данного ресурса. PHP SAPI прозрачно удаляет тело сущности
из ответов HEAD, поэтому это поведение не влияет на подавляющее большинство пользователей.

Однако разработчики, использующие Triangle Router вне веб-среды SAPI (например, пользовательский сервер),
НЕ ДОЛЖНЫ отправлять тела сущностей, сгенерированные в ответ на запросы HEAD. Если вы не являетесь пользователем SAPI, это
*ваша ответственность*; Triangle Router не имеет полномочий, чтобы помешать вам нарушить HTTP в таких случаях.

Наконец, обратите внимание, что приложения МОГУТ всегда указывать свой собственный маршрут метода HEAD для данного ресурса,
чтобы полностью обойти это поведение.

### Авторы

Эта библиотека основана на маршрутизаторах, реализованных [Levi Morrison][levi] и [Nikita Popov][nikic].

[2616-511]: http://www.w3.org/Protocols/rfc2616/rfc2616-sec5.html#sec5.1.1 "RFC 2616 Section 5.1.1"
[triangle_web]: https://github.com/Triangle-org/Web
[blog_post]: http://nikic.github.io/2014/02/18/Fast-request-routing-using-regular-expressions.html
[levi]: https://github.com/morrisonlevi
[nikic]: https://github.com/nikic

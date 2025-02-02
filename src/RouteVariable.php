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

namespace Triangle\Router;

class RouteVariable
{
    /** @var string */
    public string $method;

    /** @var string */
    public string $path = '';

    /** @var callable|mixed */
    public $callback = null;

    /** @var RouteObject */
    public RouteObject $object;

    /** @var string */
    public string $regex;

    /** @var array */
    public array $variables;

    /**
     * Конструктор маршрута.
     *
     * @param string $method HTTP-метод
     * @param string $path Путь маршрута
     * @param callable|mixed $callback Обработчик маршрута
     * @param RouteObject $object Объект-адаптер для Triangle
     * @param string $regex Регулярное выражение для парсера
     * @param array $variables Переменные машрута
     */
    public function __construct(string $method, string $path, mixed $callback, string $regex, array $variables, RouteObject $object)
    {
        $this->method = $method;
        $this->path = $path;
        $this->callback = $callback;
        $this->object = $object;
        $this->regex = $regex;
        $this->variables = $variables;

    }

    /**
     * Tests whether this route matches the given string.
     *
     * @param string $str
     *
     * @return bool
     */
    public function matches($str)
    {
        $regex = '~^' . $this->regex . '$~';
        return (bool)preg_match($regex, $str);
    }
}

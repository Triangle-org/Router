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

namespace Triangle\Router\DataGenerator;

use Triangle\Router\BadRouteException;
use Triangle\Router\DataGenerator;
use Triangle\Router\RouteObject;
use Triangle\Router\RouteStatic;
use Triangle\Router\RouteVariable;

abstract class RegexBasedAbstract implements DataGenerator
{
    /** @var mixed[][] */
    protected $staticRoutes = [];

    /** @var RouteVariable[][] */
    protected $methodToRegexToRoutesMap = [];

    /** @var RouteObject[] */
    protected $routeObjects = [];

    /**
     * @return int
     */
    abstract protected function getApproxChunkSize();

    /**
     * @return mixed[]
     */
    abstract protected function processChunk($regexToRoutesMap);

    public function addRoute(string $method, string $path, array $data, mixed $callback, RouteObject $object)
    {
        $this->routeObjects[] = $object;
        if ($this->isStaticRoute($data)) {
            $this->addStaticRoute($method, $path, $data, $callback, $object);
        } else {
            $this->addVariableRoute($method, $path, $data, $callback, $object);
        }
    }

    /**
     * @return mixed[]
     */
    public function getData()
    {
        if (empty($this->methodToRegexToRoutesMap)) {
            return [$this->staticRoutes, []];
        }

        return [$this->staticRoutes, $this->generateVariableRouteData()];
    }

    /**
     * @return RouteObject[]
     */
    public function getRoutes(): array
    {
        return $this->routeObjects;
    }

    /**
     * @return mixed[][]
     */
    public function getStaticRoutes(): array
    {
        return $this->staticRoutes;
    }

    /**
     * @return mixed[]
     */
    private function generateVariableRouteData()
    {
        $data = [];
        foreach ($this->methodToRegexToRoutesMap as $method => $regexToRoutesMap) {
            $chunkSize = $this->computeChunkSize(count($regexToRoutesMap));
            $chunks = array_chunk($regexToRoutesMap, $chunkSize, true);
            $data[$method] = array_map([$this, 'processChunk'], $chunks);
        }
        return $data;
    }

    /**
     * @param int
     * @return int
     */
    private function computeChunkSize($count)
    {
        $numParts = max(1, round($count / $this->getApproxChunkSize()));
        return (int)ceil($count / $numParts);
    }

    /**
     * @param mixed[]
     * @return bool
     */
    private function isStaticRoute($data)
    {
        return count($data) === 1 && is_string($data[0]);
    }

    private function addStaticRoute(string $method, string $realPath, array $data, mixed $callback, RouteObject $object)
    {
        $path = $data[0];

        if (isset($this->staticRoutes[$method][$path])) {
            throw new BadRouteException(sprintf('Cannot register two routes matching "%s" for method "%s"', $path, $method));
        }

        if (isset($this->methodToRegexToRoutesMap[$method])) {
            foreach ($this->methodToRegexToRoutesMap[$method] as $route) {
                if ($route->matches($path)) {
                    throw new BadRouteException(sprintf('Static route "%s" is shadowed by previously defined variable route "%s" for method "%s"', $path, $route->regex, $method));
                }
            }
        }

        $this->staticRoutes[$method][$path] = new RouteStatic($method, $realPath, $callback, $object);
    }

    private function addVariableRoute(string $method, string $path, array $data, mixed $callback, RouteObject $object)
    {
        list($regex, $variables) = $this->buildRegexForRoute($data);

        if (isset($this->methodToRegexToRoutesMap[$method][$regex])) {
            throw new BadRouteException(sprintf('Cannot register two routes matching "%s" for method "%s"', $regex, $method));
        }

        $this->methodToRegexToRoutesMap[$method][$regex] = new RouteVariable($method, $path, $callback, $regex, $variables, $object);
    }

    /**
     * @param mixed[]
     * @return mixed[]
     */
    private function buildRegexForRoute($routeData)
    {
        $regex = '';
        $variables = [];
        foreach ($routeData as $part) {
            if (is_string($part)) {
                $regex .= preg_quote($part, '~');
                continue;
            }

            list($varName, $regexPart) = $part;

            if (isset($variables[$varName])) {
                throw new BadRouteException(sprintf(
                    'Cannot use the same placeholder "%s" twice', $varName
                ));
            }

            if ($this->regexHasCapturingGroups($regexPart)) {
                throw new BadRouteException(sprintf(
                    'Regex "%s" for parameter "%s" contains a capturing group',
                    $regexPart, $varName
                ));
            }

            $variables[$varName] = $varName;
            $regex .= '(' . $regexPart . ')';
        }

        return [$regex, $variables];
    }

    /**
     * @param string
     * @return bool
     */
    private function regexHasCapturingGroups($regex)
    {
        if (!str_contains($regex, '(')) {
            // Needs to have at least a ( to contain a capturing group
            return false;
        }

        // Semi-accurate detection for capturing groups
        return (bool)preg_match(
            '~
                (?:
                    \(\?\(
                  | \[ [^\]\\\\]* (?: \\\\ . [^\]\\\\]* )* \]
                  | \\\\ .
                ) (*SKIP)(*FAIL) |
                \(
                (?!
                    \? (?! <(?![!=]) | P< | \' )
                  | \*
                )
            ~x',
            $regex
        );
    }
}

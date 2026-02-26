<?php

namespace App\Core;

class Router
{
    private array $routes = [
        'GET' => [],
        'POST' => [],
    ];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(string $method, string $path)
    {
        $path = rtrim($path, '/') ?: '/';

        $routes = $this->routes[$method] ?? [];

        if (isset($routes[$path])) {
            return $routes[$path]();
        }

        http_response_code(404);
        echo '404 Not Found';
        return null;
    }
}


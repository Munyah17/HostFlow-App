<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Small router: method + path pattern ("/customers/{id}") + middleware list.
 * Middleware: "auth", "guest", "role:super_admin,admin", "staff", "super", "client", "nocsrf".
 */
final class Router
{
    private array $routes = [];
    private array $groupStack = [];

    public function get(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
    }

    public function add(string $method, string $path, callable|array $handler, array $middleware = [], bool $allowOverride = true): void
    {
        $prefix = '';
        $groupMiddleware = [];
        foreach ($this->groupStack as $group) {
            $prefix .= $group['prefix'] ?? '';
            $groupMiddleware = array_merge($groupMiddleware, $group['middleware'] ?? []);
        }
        $full = '/' . trim($prefix . '/' . trim($path, '/'), '/');
        $key = $method . ' ' . $full;
        if (!$allowOverride && isset($this->routes[$key])) {
            throw new \RuntimeException("Route already registered: {$key}");
        }
        $this->routes[$key] = [
            'method' => $method,
            'path' => $full,
            'regex' => '#^' . preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[^/]+)', $full) . '$#',
            'handler' => $handler,
            'middleware' => array_merge($groupMiddleware, $middleware),
        ];
    }

    public function has(string $method, string $path): bool
    {
        return isset($this->routes[$method . ' ' . '/' . trim($path, '/')]);
    }

    public function routes(): array
    {
        return $this->routes;
    }

    public function dispatch(string $method, string $path): mixed
    {
        $allowed = false;
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $m)) {
                continue;
            }
            if ($route['method'] !== $method) {
                $allowed = true;
                continue;
            }
            $params = [];
            foreach ($m as $k => $v) {
                if (is_string($k)) {
                    $params[$k] = ctype_digit($v) ? (int) $v : $v;
                }
            }
            Middleware::run($route['middleware'], $method);
            $handler = $route['handler'];
            if (is_array($handler) && is_string($handler[0])) {
                $handler = [new $handler[0](), $handler[1]];
            }
            return $handler(...array_values($params));
        }
        throw new HttpException($allowed ? 405 : 404);
    }
}

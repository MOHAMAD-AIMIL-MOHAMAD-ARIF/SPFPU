<?php
declare(strict_types=1);

namespace SPFPU\Core;

final class Router
{
    private array $routes = [];

    public function get(string $path, callable|array $handler): void { $this->add('GET', $path, $handler); }
    public function post(string $path, callable|array $handler): void { $this->add('POST', $path, $handler); }
    private function add(string $method, string $path, callable|array $handler): void
    {
        $pattern = preg_replace('#\{([a-z_]+)}#i', '(?P<$1>[^/]+)', $path);
        $this->routes[] = [$method, '#^' . $pattern . '/?$#', $handler];
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = '/' . trim(parse_url($uri, PHP_URL_PATH) ?: '/', '/');
        if ($path === '//') $path = '/';
        if ($method === 'POST') Csrf::verify();
        foreach ($this->routes as [$verb, $pattern, $handler]) {
            if ($verb !== $method || !preg_match($pattern, $path, $matches)) continue;
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            if (is_array($handler)) {
                [$class, $action] = $handler;
                (new $class())->$action(...array_values($params));
            } else {
                $handler(...array_values($params));
            }
            return;
        }
        Http::abort(404, 'Halaman yang diminta tidak ditemui.');
    }
}

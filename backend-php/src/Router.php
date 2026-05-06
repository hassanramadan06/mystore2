<?php
// Tiny path-pattern router. Patterns may contain {name} placeholders, e.g. /api/products/{id}.
// Numeric placeholders are passed to the handler as integers.

namespace MyStore;

class Router
{
    /** @var array<int, array{0:string,1:string,2:callable}> */
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = [strtoupper($method), $pattern, $handler];
    }

    public function get(string $p, callable $h)    { $this->add('GET',    $p, $h); }
    public function post(string $p, callable $h)   { $this->add('POST',   $p, $h); }
    public function put(string $p, callable $h)    { $this->add('PUT',    $p, $h); }
    public function delete(string $p, callable $h) { $this->add('DELETE', $p, $h); }
    public function patch(string $p, callable $h)  { $this->add('PATCH',  $p, $h); }

    public function dispatch(string $method, string $path): void
    {
        $method = strtoupper($method);

       if ($method === 'POST') {
          $bodyMethod = $_POST['_method'] ?? null;
          if ($bodyMethod) {
              $method = strtoupper($bodyMethod);
          }
        }
        // Strip query string and trailing slash.
        $path = '/' . trim(parse_url($path, PHP_URL_PATH) ?? '/', '/');

        // First pass: find a route whose pattern AND method both match.
        // Track whether any route matched the path so we can distinguish 404 from 405.
        $pathMatched = false;
        foreach ($this->routes as [$m, $pattern, $handler]) {
            $params = [];
            if (!$this->match($pattern, $path, $params)) continue;
            $pathMatched = true;
            if ($m === $method) {
                $handler(...array_values($params));
                return;
            }
        }
        if ($pathMatched) {
            Helpers::error('Method not allowed', 405);
        }
        Helpers::error('Not found: ' . $method . ' ' . $path, 404);
    }

    private function match(string $pattern, string $path, array &$params): bool
    {
        $regex = preg_replace('#\{([a-zA-Z_]+)\}#', '(?P<$1>[^/]+)', $pattern);
        if (!preg_match('#^' . $regex . '$#', $path, $m)) return false;
        foreach ($m as $k => $v) {
            if (!is_int($k)) $params[$k] = ctype_digit($v) ? (int)$v : $v;
        }
        return true;
    }
}

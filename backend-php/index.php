<?php
// Front controller for the MyStore PHP API.
// Apache rewrites every `/api/...` request to this file via .htaccess.
// All routes return JSON. Response shapes match the previous .NET API exactly,
// so the existing frontend works unchanged once `js/config.js` points here.

declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// 1. Load config + simple PSR-4-style autoloader for the MyStore namespace.
$root = __DIR__;
$configFile = $root . '/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'config.php missing — copy config.example.php to config.php and fill in your DB credentials.']);
    exit;
}
$GLOBALS['MYSTORE_CONFIG'] = require $configFile;

spl_autoload_register(function (string $class) use ($root) {
    if (!str_starts_with($class, 'MyStore\\')) return;
    $rel = substr($class, strlen('MyStore\\'));
    $path = $root . '/src/' . str_replace('\\', '/', $rel) . '.php';
    if (is_file($path)) require $path;
});

use MyStore\Helpers;
use MyStore\Router;
use MyStore\Controllers\AuthController;
use MyStore\Controllers\CartController;
use MyStore\Controllers\CategoriesController;
use MyStore\Controllers\OrdersController;
use MyStore\Controllers\ProductsController;

// 2. CORS (handles OPTIONS preflight and exits before routing).
Helpers::applyCors();

// 3. Routes.
$r = new Router();

// Health
$r->get('/health',     fn() => Helpers::json(['status' => 'ok']));
$r->get('/api/health', fn() => Helpers::json(['status' => 'ok']));

// Auth
$auth = new AuthController();
$r->post('/api/auth/register', fn() => $auth->register());
$r->post('/api/auth/login',    fn() => $auth->login());
$r->get ('/api/users/me',      fn() => $auth->me());

// Categories
$cats = new CategoriesController();
$r->get   ('/api/categories',        fn()           => $cats->list());
$r->get   ('/api/categories/{id}',   fn(int $id)    => $cats->get($id));
$r->post  ('/api/categories',        fn()           => $cats->create());
$r->put   ('/api/categories/{id}',   fn(int $id)    => $cats->update($id));
$r->delete('/api/categories/{id}',   fn(int $id)    => $cats->delete($id));

// Products
$prods = new ProductsController();
$r->get   ('/api/products',          fn()           => $prods->search());
$r->get   ('/api/products/featured', fn()           => $prods->featured());
$r->get   ('/api/products/{id}',     fn(int $id)    => $prods->get($id));
$r->post  ('/api/products',          fn()           => $prods->create());
$r->put   ('/api/products/{id}',     fn(int $id)    => $prods->update($id));
$r->delete('/api/products/{id}',     fn(int $id)    => $prods->delete($id));

// Cart
$cart = new CartController();
$r->get   ('/api/cart',                       fn()        => $cart->get());
$r->post  ('/api/cart/items',                 fn()        => $cart->add());
$r->put   ('/api/cart/items/{productId}',     fn(int $pid) => $cart->update($pid));
$r->delete('/api/cart/items/{productId}',     fn(int $pid) => $cart->remove($pid));
$r->delete('/api/cart',                       fn()        => $cart->clear());

// Orders
$orders = new OrdersController();
$r->post ('/api/orders/checkout',     fn()        => $orders->checkout());
$r->get  ('/api/orders',              fn()        => $orders->mine());
$r->get  ('/api/orders/all',          fn()        => $orders->listAll());
$r->get  ('/api/orders/{id}',         fn(int $id) => $orders->get($id));
$r->put  ('/api/orders/{id}/status',  fn(int $id) => $orders->updateStatus($id));
$r->patch('/api/orders/{id}/status',  fn(int $id) => $orders->updateStatus($id));

// 4. Dispatch with a top-level error handler that always returns JSON.
//    The base prefix is auto-detected from SCRIPT_NAME so the same code works whether
//    deployed at the document root (e.g. /api/...) or under a subdirectory
//    (e.g. http://localhost/mystore/backend-php/api/... during XAMPP local dev).
try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

    $base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    // If routes are reached via a sibling /api folder (e.g. public_html/api/index.php),
    // strip the trailing /api because routes are already declared with /api prefix.
    if (str_ends_with($base, '/api')) {
        $base = substr($base, 0, -4);
    }
    if ($base === '/' || $base === '.') {
        $base = '';
    }
    if ($base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base)) ?: '/';
    }

    $r->dispatch($method, $path);
} catch (\Throwable $e) {
    error_log('[mystore] ' . $e);
    Helpers::json(['error' => $e->getMessage()], 500);
}

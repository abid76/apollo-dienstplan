<?php

declare(strict_types=1);

use App\Core\Router;
use App\Controller\ShiftController;
use App\Controller\RoleController;
use App\Controller\EmployeeController;
use App\Controller\RuleController;
use App\Controller\PlanController;

error_reporting(E_ALL);
ini_set('display_errors', '1');

$baseDir = dirname(__DIR__);
require $baseDir . '/vendor/autoload.php';

spl_autoload_register(function (string $class) use ($baseDir): void {
    $prefix = 'App\\';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relativeClass = substr($class, $len);
    $file = $baseDir . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

$router = new Router();

// Home
$router->get('/', function () {
    require dirname(__DIR__) . '/views/layout.php';
});

// Shifts
$router->get('/shifts', [new ShiftController(), 'index']);
$router->get('/shifts/create', [new ShiftController(), 'create']);
$router->post('/shifts/create', [new ShiftController(), 'store']);
$router->get('/shifts/edit', [new ShiftController(), 'edit']);
$router->post('/shifts/edit', [new ShiftController(), 'update']);
$router->post('/shifts/delete', [new ShiftController(), 'delete']);

// Roles
$router->get('/roles', [new RoleController(), 'index']);
$router->get('/roles/create', [new RoleController(), 'create']);
$router->post('/roles/create', [new RoleController(), 'store']);
$router->get('/roles/edit', [new RoleController(), 'edit']);
$router->post('/roles/edit', [new RoleController(), 'update']);
$router->post('/roles/delete', [new RoleController(), 'delete']);

// Employees
$router->get('/employees', [new EmployeeController(), 'index']);
$router->get('/employees/create', [new EmployeeController(), 'create']);
$router->post('/employees/create', [new EmployeeController(), 'store']);
$router->get('/employees/edit', [new EmployeeController(), 'edit']);
$router->post('/employees/edit', [new EmployeeController(), 'update']);
$router->post('/employees/delete', [new EmployeeController(), 'delete']);

// Rules
$router->get('/rules', [new RuleController(), 'index']);
$router->get('/rules/create', [new RuleController(), 'create']);
$router->post('/rules/create', [new RuleController(), 'store']);
$router->get('/rules/edit', [new RuleController(), 'edit']);
$router->post('/rules/edit', [new RuleController(), 'update']);
$router->post('/rules/delete', [new RuleController(), 'delete']);

// Plan
$router->get('/plan', [new PlanController(), 'index']);
$router->get('/plan/create', [new PlanController(), 'form']);
$router->post('/plan/generate', [new PlanController(), 'generate']);
$router->get('/plan/show', [new PlanController(), 'show']);
$router->post('/plan/delete', [new PlanController(), 'delete']);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$router->dispatch($method, $path);


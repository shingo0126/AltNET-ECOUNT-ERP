<?php
/**
 * AltNET Ecount ERP - Main Entry Point / Router
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

// Force UTF-8 output
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');

// 대한민국 시간대 설정 (KST = UTC+9)
date_default_timezone_set('Asia/Seoul');

// Load core files
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Session.php';
require_once __DIR__ . '/core/CSRF.php';
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/core/AuditLog.php';
require_once __DIR__ . '/core/Helper.php';

// Start session
Session::start();

// Get requested page
$page = getParam('page', 'dashboard');
$action = getParam('action', 'index');

// API routes (AJAX)
if (strpos($page, 'api/') === 0) {
    $apiFile = __DIR__ . '/api/' . str_replace('api/', '', $page) . '.php';
    if (file_exists($apiFile)) {
        require_once $apiFile;
    } else {
        jsonResponse(['error' => 'API not found'], 404);
    }
    exit;
}

// Public pages (no auth required)
$publicPages = ['login'];

if (!in_array($page, $publicPages)) {
    Auth::requireLogin();
}

// Route to controller
$controllerMap = [
    'login'      => 'AuthController',
    'logout'     => 'AuthController',
    'dashboard'  => 'DashboardController',
    'sales'      => 'SalesController',
    'companies'  => 'CompanyController',
    'vendors'    => 'VendorController',
    'items'      => 'ItemController',
    'users'      => 'UserController',
    'backup'     => 'BackupController',
    'audit'      => 'AuditController',
];

if ($page === 'logout') {
    Auth::logout();
    redirect('?page=login');
}

if (isset($controllerMap[$page])) {
    $controllerFile = __DIR__ . '/controllers/' . $controllerMap[$page] . '.php';
    if (file_exists($controllerFile)) {
        require_once $controllerFile;
        $controller = new $controllerMap[$page]();
        if (method_exists($controller, $action)) {
            $controller->$action();
        } else {
            $controller->index();
        }
    } else {
        echo "Controller not found: {$controllerMap[$page]}";
    }
} else {
    redirect('?page=dashboard');
}

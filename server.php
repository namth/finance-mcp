#!/usr/bin/env php
<?php

/**
 * SimpleFinance MCP Server (PHP 8.3 + MySQL / Multi-User)
 * Entrypoint chạy qua STDIN / STDOUT JSON-RPC 2.0
 * Sử dụng: php server.php [--key=YOUR_API_KEY]
 */

// Đặt timezone mặc định
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Bật thông báo lỗi nhưng chuyển sang STDERR để không làm hỏng phản hồi JSON-RPC trên STDOUT
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Autoloader đơn giản cho namespace SimpleFinance
spl_autoload_register(function ($class) {
    $prefix = 'SimpleFinance\\';
    $baseDir = __DIR__ . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Kiểm tra tham số API Key từ CLI arguments hoặc biến môi trường
$apiKey = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--key=')) {
        $apiKey = substr($arg, 6);
    } elseif (str_starts_with($arg, '--api-key=')) {
        $apiKey = substr($arg, 10);
    }
}
if (!$apiKey) {
    $apiKey = getenv('SF_API_KEY') ?: null;
}

$currentUser = null;
if ($apiKey) {
    try {
        $currentUser = (new \SimpleFinance\Models\User())->findByApiKey($apiKey);
    } catch (\Throwable $e) {
        fwrite(STDERR, "Warning: Failed to load user for API key: " . $e->getMessage() . "\n");
    }
}

// Chạy MCP Server
try {
    $server = new \SimpleFinance\McpServer($currentUser);
    $server->run();
} catch (\Throwable $e) {
    fwrite(STDERR, "Fatal Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    exit(1);
}

<?php

/**
 * SimpleFinance MCP Server over HTTP & SSE (Multi-User Secured)
 * Endpoint: https://financemcp.oa.io.vn/mcp.php?key=YOUR_API_KEY
 */

// Đặt timezone
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Bật error log nhưng tắt display errors để không làm hỏng payload JSON
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Cấu hình CORS mở để các client (Gemini, Claude, Antigravity, Cursor...) kết nối được
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, Accept, X-Requested-With, X-API-Key");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Autoloader cho namespace SimpleFinance
spl_autoload_register(function ($class) {
    $prefix = 'SimpleFinance\\';
    $baseDir = __DIR__ . '/src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});

// Trích xuất API Key từ Query Params hoặc Header
$apiKey = $_GET['key'] ?? $_GET['api_key'] ?? '';
if (empty($apiKey)) {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
        $apiKey = $matches[1];
    } elseif (!empty($headers['X-API-Key'] ?? $headers['x-api-key'] ?? '')) {
        $apiKey = $headers['X-API-Key'] ?? $headers['x-api-key'];
    }
}

// Xác thực người dùng qua API Key
$authenticatedUser = null;
if (!empty($apiKey)) {
    try {
        $userModel = new \SimpleFinance\Models\User();
        $authenticatedUser = $userModel->findByApiKey($apiKey);
    } catch (\Throwable $e) {
        error_log("MCP Auth DB Error: " . $e->getMessage());
    }
}

$method = $_SERVER['REQUEST_METHOD'];

// 1. XỬ LÝ POST: JSON-RPC 2.0 (Stateless HTTP Transport)
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $request = json_decode($rawInput, true);

    header('Content-Type: application/json; charset=utf-8');

    if (!is_array($request)) {
        echo json_encode([
            'jsonrpc' => '2.0',
            'id'      => null,
            'error'   => [
                'code'    => -32700,
                'message' => 'Parse error: Invalid JSON input',
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Yêu cầu xác thực API key
    if (!$authenticatedUser) {
        http_response_code(401);
        echo json_encode([
            'jsonrpc' => '2.0',
            'id'      => $request['id'] ?? null,
            'error'   => [
                'code'    => -32001,
                'message' => 'Unauthorized: Cần cung cấp API key hợp lệ (?key=... hoặc Authorization: Bearer <key>). Bạn có thể lấy API key tại trang Cá Nhân (profile.php).',
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $server = new \SimpleFinance\McpServer($authenticatedUser);
        $response = $server->handleRequest($request);

        if ($response !== null) {
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
        }
    } catch (\Throwable $e) {
        echo json_encode([
            'jsonrpc' => '2.0',
            'id'      => $request['id'] ?? null,
            'error'   => [
                'code'    => -32603,
                'message' => 'Internal server error: ' . $e->getMessage(),
            ]
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// 2. XỬ LÝ GET: Hỗ trợ SSE (Server-Sent Events) hoặc Trang Trạng Thái
if ($method === 'GET') {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';

    // Luồng SSE
    if (str_contains($accept, 'text/event-stream')) {
        if (!$authenticatedUser) {
            http_response_code(401);
            header('Content-Type: text/plain; charset=utf-8');
            echo "HTTP 401 Unauthorized: Cần cung cấp API key hợp lệ trong URL: ?key=YOUR_API_KEY hoặc Authorization header.\n";
            exit;
        }

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        if (ob_get_level() > 0) {
            ob_end_flush();
        }

        // Gửi URI để client gửi POST messages kèm API key
        $postEndpoint = '/mcp.php?key=' . urlencode($apiKey);
        echo "event: endpoint\n";
        echo "data: " . $postEndpoint . "\n\n";
        flush();

        // Giữ kết nối
        while (true) {
            if (connection_aborted()) {
                break;
            }
            echo ": keep-alive\n\n";
            flush();
            sleep(25);
        }
        exit;
    }

    // Nếu truy cập bằng trình duyệt thông thường: Hiển thị trang thông tin endpoint
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>SimpleFinance MCP Server</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-slate-50 flex items-center justify-center min-h-screen p-6 font-sans">
        <div class="max-w-md w-full bg-white p-6 rounded-2xl shadow-xl border border-slate-200 text-center">
            <div class="w-14 h-14 rounded-2xl <?= $authenticatedUser ? 'bg-emerald-600' : 'bg-amber-500' ?> text-white flex items-center justify-center mx-auto mb-4 shadow-md">
                <?php if ($authenticatedUser): ?>
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                    </svg>
                <?php else: ?>
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                <?php endif; ?>
            </div>
            
            <h1 class="text-xl font-bold text-slate-900">SimpleFinance MCP Server</h1>
            
            <?php if ($authenticatedUser): ?>
                <div class="mt-2 inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 text-emerald-700 rounded-full text-xs font-semibold">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    Đã xác thực: <?= htmlspecialchars($authenticatedUser['full_name']) ?> (@<?= htmlspecialchars($authenticatedUser['username']) ?>)
                </div>

                <div class="mt-4 p-3 bg-slate-100 rounded-xl text-left text-xs font-mono text-slate-700 break-all">
                    <span class="text-slate-400 font-sans block mb-1 font-bold">Endpoint MCP Cá Nhân của bạn:</span>
                    https://<?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'financemcp.oa.io.vn') ?>/mcp.php?key=<?= htmlspecialchars($apiKey) ?>
                </div>

                <div class="mt-4 text-xs text-slate-500 text-left space-y-1.5">
                    <p>&bull; <strong>Giao thức:</strong> JSON-RPC 2.0 (POST) & SSE (GET)</p>
                    <p>&bull; <strong>Dữ liệu:</strong> Được bảo mật và phân quyền theo tài khoản của bạn</p>
                    <p>&bull; <strong>Tích hợp:</strong> Gemini, Claude Desktop, Antigravity, Cursor</p>
                </div>

                <div class="mt-6 flex items-center justify-center gap-4">
                    <a href="index.php" class="text-xs font-bold text-emerald-600 hover:text-emerald-700">
                        &larr; Vào Bảng Điều Khiển Web
                    </a>
                    <a href="profile.php" class="text-xs font-bold text-slate-600 hover:text-slate-800">
                        Xem Profile & API Key
                    </a>
                </div>
            <?php else: ?>
                <div class="mt-2 inline-flex items-center gap-1.5 px-3 py-1 bg-amber-50 text-amber-700 rounded-full text-xs font-semibold">
                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                    Yêu Cầu Xác Thực API Key
                </div>

                <p class="text-xs text-slate-500 mt-3 leading-relaxed">
                    Hệ thống đã được nâng cấp lên kiến trúc đa người dùng. Mỗi tài khoản có API Key riêng để tích hợp an toàn với Gemini, Claude hoặc các AI agent.
                </p>

                <div class="mt-4 p-3 bg-amber-50/70 border border-amber-200 rounded-xl text-left text-xs text-amber-900">
                    <p class="font-bold mb-1">Cách kết nối MCP:</p>
                    <p class="font-mono text-[11px] text-amber-800">https://financemcp.oa.io.vn/mcp.php?key=YOUR_API_KEY</p>
                </div>

                <div class="mt-6 flex flex-col gap-2">
                    <a href="login.php" class="w-full py-2.5 px-4 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-sm">
                        Đăng Nhập Web Admin để lấy API Key
                    </a>
                    <a href="register.php" class="w-full py-2.5 px-4 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition">
                        Đăng Ký Tài Khoản Mới
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
    exit;
}

<?php

/**
 * SimpleFinance Auto-Deploy Webhook API
 * Endpoint: https://financemcp.oa.io.vn/deploy.php
 * 
 * Hỗ trợ các phương thức gọi:
 * 1. AI Agent / cURL / Trình duyệt:
 *    - POST hoặc GET kèm ?token=YOUR_TOKEN
 *    - Header: X-Deploy-Token: YOUR_TOKEN hoặc Authorization: Bearer YOUR_TOKEN
 * 2. GitHub Webhook tự động khi push code:
 *    - Payload URL: https://financemcp.oa.io.vn/deploy.php
 *    - Content type: application/json
 *    - Secret: YOUR_TOKEN (tự động kiểm tra HMAC SHA-256 qua header X-Hub-Signature-256)
 */

date_default_timezone_set('Asia/Ho_Chi_Minh');

// Bật error log nhưng tắt display errors để không làm hỏng payload JSON
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// 1. Tải cấu hình
$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    $configFile = __DIR__ . '/config.example.php';
}

if (!file_exists($configFile)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi: Không tìm thấy file cấu hình config.php',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$config = require $configFile;
$deployConfig = $config['deploy'] ?? [
    'enabled'      => true,
    'secret_token' => 'sf_dep_8f1a3b5c7e9d2f4a6b8c0e1d3f5a7b9c',
    'branch'       => 'main',
];

if (empty($deployConfig['enabled'])) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Chức năng Auto-Deploy đang bị tắt trong file config.php',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$secretToken = $deployConfig['secret_token'] ?? '';
$defaultBranch = $deployConfig['branch'] ?? 'main';

// 2. Kiểm tra xác thực (Token hoặc GitHub Webhook Signature)
$headers = function_exists('getallheaders') ? getallheaders() : [];
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
$customTokenHeader = $headers['X-Deploy-Token'] ?? $headers['x-deploy-token'] ?? '';
$githubSignature = $headers['X-Hub-Signature-256'] ?? $headers['x-hub-signature-256'] ?? '';
$githubEvent = $headers['X-GitHub-Event'] ?? $headers['x-github-event'] ?? '';

$providedToken = $_GET['token'] ?? $_POST['token'] ?? $customTokenHeader;
if (empty($providedToken) && preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
    $providedToken = $matches[1];
}

$rawPayload = file_get_contents('php://input');
$isAuthenticated = false;
$isGithubWebhook = false;

// Cách 1: Xác thực qua Secret Token
if (!empty($secretToken) && !empty($providedToken) && hash_equals($secretToken, $providedToken)) {
    $isAuthenticated = true;
}

// Cách 2: Xác thực qua GitHub Webhook Signature (HMAC SHA256)
if (!$isAuthenticated && !empty($githubSignature) && !empty($secretToken)) {
    $expectedSignature = 'sha256=' . hash_hmac('sha256', $rawPayload, $secretToken);
    if (hash_equals($expectedSignature, $githubSignature)) {
        $isAuthenticated = true;
        $isGithubWebhook = true;
    }
}

// Nếu truy cập từ trình duyệt mà chưa có token: Hiển thị form nhập token
$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
$isHtmlRequest = str_contains($accept, 'text/html') && empty($isGithubWebhook);

if (!$isAuthenticated) {
    http_response_code(403);
    if ($isHtmlRequest) {
        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!DOCTYPE html>
        <html lang="vi">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Auto Deploy - SimpleFinance</title>
            <script src="https://cdn.tailwindcss.com"></script>
        </head>
        <body class="bg-slate-100 flex items-center justify-center min-h-screen p-4 font-sans">
            <div class="max-w-md w-full bg-white p-6 rounded-2xl shadow-xl border border-slate-200 text-center">
                <div class="w-12 h-12 bg-red-100 text-red-600 rounded-2xl flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m0 0v2m0-2h2m-2 0H10m4-11a4 4 0 00-8 0v4h8V4z"></path>
                    </svg>
                </div>
                <h1 class="text-lg font-bold text-slate-900">Yêu Cầu Deploy Token</h1>
                <p class="text-xs text-slate-500 mt-2">Vui lòng cung cấp mã token bí mật trong cấu hình để kích hoạt cập nhật từ GitHub.</p>
                <form method="GET" class="mt-4 space-y-3">
                    <input type="password" name="token" placeholder="Nhập Deploy Token..." required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500 outline-none">
                    <button type="submit" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs transition shadow-sm">
                        Xác Thực & Xem Trạng Thái
                    </button>
                </form>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Truy cập bị từ chối: Token xác thực không hợp lệ hoặc bị thiếu.',
        'hint'    => 'Cung cấp token qua ?token=YOUR_TOKEN hoặc Header X-Deploy-Token / Authorization: Bearer',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// 3. Kiểm tra các hàm thực thi lệnh shell
if (!function_exists('exec') && !function_exists('shell_exec')) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi: Máy chủ đã tắt hàm exec/shell_exec trong php.ini (disable_functions). Vui lòng bật hàm exec trên hosting để sử dụng Auto Deploy.',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// 4. Khóa tiến trình chống chạy đồng thời (Concurrency Lock)
$lockFile = __DIR__ . '/deploy.lock';
$lockFp = fopen($lockFile, 'w+');
if (!$lockFp || !flock($lockFp, LOCK_EX | LOCK_NB)) {
    http_response_code(429);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Một tiến trình deploy khác đang diễn ra. Vui lòng thử lại sau vài giây.',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// 5. Xác định nhánh Git & Hành động
$targetBranch = $_GET['branch'] ?? $_POST['branch'] ?? $defaultBranch;

// Nếu là GitHub Webhook push event, tự động lấy nhánh từ payload (hỗ trợ cả JSON và Form Urlencoded)
if ($isGithubWebhook) {
    $payloadData = null;
    if (!empty($_POST['payload'])) {
        $payloadData = json_decode($_POST['payload'], true);
    } elseif (!empty($rawPayload)) {
        $payloadData = json_decode($rawPayload, true);
    }
    if (isset($payloadData['ref'])) {
        $pushedBranch = str_replace('refs/heads/', '', $payloadData['ref']);
        if (!empty($pushedBranch)) {
            $targetBranch = $pushedBranch;
        }
    }
}

// Lọc ký tự hợp lệ cho tên nhánh
$targetBranch = preg_replace('/[^a-zA-Z0-9_\-\.\/]/', '', $targetBranch);
if (empty($targetBranch)) {
    $targetBranch = 'main';
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'pull';

$repoDir = escapeshellarg(__DIR__);
$outputLog = [];
$startTime = microtime(true);

function runCommand(string $cmd, &$outputLog): int {
    $output = [];
    $returnVar = 0;
    exec($cmd . ' 2>&1', $output, $returnVar);
    $outputLog[] = [
        'command' => $cmd,
        'output'  => implode("\n", $output),
        'status'  => $returnVar,
    ];
    return $returnVar;
}

// Đăng ký an toàn thư mục git
@exec("git config --global --add safe.directory {$repoDir} 2>&1");

// Kiểm tra xem máy chủ đã có git repository chưa
$checkGit = [];
$isGitRepo = (exec("cd {$repoDir} && git rev-parse --is-inside-work-tree 2>&1", $checkGit) === 'true');

if (!$isGitRepo) {
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    @unlink($lockFile);

    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Thư mục trên máy chủ chưa phải là một Git repository.',
        'hint'    => "Vui lòng SSH vào máy chủ và khởi tạo git clone từ GitHub: git clone <REPO_URL> . hoặc git init && git remote add origin <REPO_URL>",
        'output'  => implode("\n", $checkGit),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Xử lý Hành động STATUS
if ($action === 'status') {
    runCommand("cd {$repoDir} && git status", $outputLog);
    runCommand("cd {$repoDir} && git log -1 --pretty=format:\"%h - %an (%ar): %s\"", $outputLog);

    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    @unlink($lockFile);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success'   => true,
        'action'    => 'status',
        'directory' => __DIR__,
        'log'       => $outputLog,
        'timestamp' => date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Xử lý Hành động MCP_INFO (Lấy thông tin MCP Endpoint cho Admin)
if ($action === 'mcp_info') {
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    @unlink($lockFile);

    header('Content-Type: application/json; charset=utf-8');
    try {
        $dbConfig = $config['db'] ?? [];
        $dsn = sprintf(
            "mysql:host=%s;port=%d;dbname=%s;charset=%s",
            $dbConfig['host'] ?? '127.0.0.1',
            $dbConfig['port'] ?? 3306,
            $dbConfig['dbname'] ?? 'simplefinance',
            $dbConfig['charset'] ?? 'utf8mb4'
        );
        $pdo = new PDO($dsn, $dbConfig['username'] ?? 'root', $dbConfig['password'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $stmt = $pdo->query("SELECT id, username, full_name, email, api_key FROM `users` ORDER BY id ASC LIMIT 1");
        $u = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($u) {
            $host = $_SERVER['HTTP_HOST'] ?? 'financemcp.oa.io.vn';
            echo json_encode([
                'success' => true,
                'user'    => [
                    'id'        => (int)$u['id'],
                    'username'  => $u['username'],
                    'full_name' => $u['full_name'],
                    'email'     => $u['email'],
                    'api_key'   => $u['api_key'],
                ],
                'mcp_url' => "https://{$host}/mcp.php?key=" . $u['api_key'],
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Chưa có tài khoản nào trong CSDL. Hãy truy cập /install.php để khởi tạo.',
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
    } catch (\Throwable $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Lỗi kết nối CSDL: ' . $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    exit;
}

// Xử lý Hành động PULL (Triển khai code mới)
// 1. git fetch origin <branch>
// 2. git reset --hard origin/<branch> (đảm bảo code sạch sẽ, không bị xung đột với các file sinh cục bộ)
$steps = [
    "cd {$repoDir} && git fetch origin {$targetBranch}",
    "cd {$repoDir} && git reset --hard origin/{$targetBranch}",
    "cd {$repoDir} && git log -1 --pretty=format:\"%h - %an (%ar): %s\"",
];

$allSuccess = true;
foreach ($steps as $cmd) {
    $status = runCommand($cmd, $outputLog);
    if ($status !== 0) {
        $allSuccess = false;
        if (str_contains($cmd, 'reset --hard')) {
            runCommand("cd {$repoDir} && git pull origin {$targetBranch}", $outputLog);
        }
    }
}

// Kiểm tra xem có yêu cầu chạy migration CSDL không (?migrate=1)
$runMigration = !empty($_GET['migrate']) || !empty($_POST['migrate']);
$migrationResult = null;

if ($runMigration) {
    try {
        $dbConfig = $config['db'] ?? [];
        $dsn = sprintf(
            "mysql:host=%s;port=%d;dbname=%s;charset=%s",
            $dbConfig['host'] ?? '127.0.0.1',
            $dbConfig['port'] ?? 3306,
            $dbConfig['dbname'] ?? 'simplefinance',
            $dbConfig['charset'] ?? 'utf8mb4'
        );
        $pdo = new PDO($dsn, $dbConfig['username'] ?? 'root', $dbConfig['password'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $sqlFile = __DIR__ . '/schema_multiuser.sql';
        if (file_exists($sqlFile)) {
            $sqlContent = file_get_contents($sqlFile);
            $pdo->exec($sqlContent);
            $migrationResult = 'Migration CSDL schema_multiuser.sql đã thực thi thành công!';
        } else {
            $migrationResult = 'Không tìm thấy file schema_multiuser.sql';
        }

        $gbSqlFile = __DIR__ . '/schema_groupbuy.sql';
        if (file_exists($gbSqlFile)) {
            $gbContent = file_get_contents($gbSqlFile);
            $pdo->exec($gbContent);
            $migrationResult .= ' & schema_groupbuy.sql thành công!';
        }

        $patchFile = __DIR__ . '/patch_event_image.sql';
        if (file_exists($patchFile)) {
            $patchContent = file_get_contents($patchFile);
            $pdo->exec($patchContent);
            $migrationResult .= ' & patch_event_image.sql thành công!';
        }

        $patchDelivered = __DIR__ . '/patch_delivered_and_timezone.sql';
        if (file_exists($patchDelivered)) {
            $patchDeliveredContent = file_get_contents($patchDelivered);
            $pdo->exec($patchDeliveredContent);
            $migrationResult .= ' & patch_delivered_and_timezone.sql thành công!';
        }
    } catch (\Throwable $e) {
        $migrationResult = 'Lỗi migration CSDL: ' . $e->getMessage();
    }
}

// Giải phóng lock file
flock($lockFp, LOCK_UN);
fclose($lockFp);
@unlink($lockFile);

$duration = round(microtime(true) - $startTime, 3);
$lastCommit = end($outputLog)['output'] ?? 'N/A';

// Trả về JSON cho API / Webhook
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success'          => $allSuccess,
    'message'          => $allSuccess ? 'Cập nhật mã nguồn từ GitHub thành công!' : 'Có cảnh báo hoặc lỗi trong quá trình git pull',
    'branch'           => $targetBranch,
    'latest_commit'    => $lastCommit,
    'migration'        => $migrationResult,
    'execution_time'   => "{$duration}s",
    'timestamp'        => date('Y-m-d H:i:s'),
    'commands_details' => $outputLog,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

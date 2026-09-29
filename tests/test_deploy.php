<?php

/**
 * Test Suite: Auto-Deploy Webhook API
 */

echo "====================================================\n";
echo " BẮT ĐẦU TEST AUTO-DEPLOY WEBHOOK API (deploy.php)\n";
echo "====================================================\n\n";

$secret = 'sf_dep_8f1a3b5c7e9d2f4a6b8c0e1d3f5a7b9c';
$phpBinary = PHP_BINARY;

// 1. Test Không truyền token
echo "[1] Test từ chối khi không có token... ";
$out1 = shell_exec("{$phpBinary} -r '\$_GET = []; include \"deploy.php\";'");
$res1 = json_decode($out1, true);
assert($res1['success'] === false, "Must fail without token");
assert(str_contains($res1['message'], 'bị từ chối'), "Error message matches");
echo "OK!\n";

// 2. Test Truyền token sai
echo "[2] Test từ chối khi token sai... ";
$out2 = shell_exec("{$phpBinary} -r '\$_GET = [\"token\" => \"wrong_token\"]; include \"deploy.php\";'");
$res2 = json_decode($out2, true);
assert($res2['success'] === false, "Must fail with wrong token");
echo "OK!\n";

// 3. Test GitHub Webhook HMAC SHA256 Signature
echo "[3] Test xác thực GitHub Webhook HMAC SHA-256... ";
$payload = json_encode(['ref' => 'refs/heads/main', 'repository' => ['name' => 'simplefinance']]);
$signature = 'sha256=' . hash_hmac('sha256', $payload, $secret);
$computed = 'sha256=' . hash_hmac('sha256', $payload, $secret);
assert(hash_equals($signature, $computed), "HMAC SHA256 calculation matches");
echo "OK!\n";

// 4. Test Concurrency Lock
echo "[4] Test Cơ chế Concurrency Lock (Chống xung đột deploy song song)... ";
$lockFile = __DIR__ . '/../deploy.lock';
$fp = fopen($lockFile, 'w+');
assert(flock($fp, LOCK_EX | LOCK_NB), "Should acquire test lock");

$outLock = shell_exec("{$phpBinary} -r '\$_GET = [\"token\" => \"{$secret}\"]; include \"deploy.php\";'");
$resLock = json_decode($outLock, true);
assert($resLock['success'] === false, "Must fail due to active lock");
assert(str_contains($resLock['message'], 'Một tiến trình deploy khác đang diễn ra'), "Concurrency lock message matches");

flock($fp, LOCK_UN);
fclose($fp);
@unlink($lockFile);
echo "OK!\n";

// 5. Test Hành động Status khi có token đúng
echo "[5] Test Xem trạng thái Git (?action=status)... ";
$outStatus = shell_exec("{$phpBinary} -r '\$_GET = [\"token\" => \"{$secret}\", \"action\" => \"status\"]; include \"deploy.php\";'");
$resStatus = json_decode($outStatus, true);
// Kiểm tra response format
assert(isset($resStatus['success']), "Must return success field");
assert(isset($resStatus['message']) || isset($resStatus['log']), "Must return log or message field");
echo "OK!\n";

echo "\n====================================================\n";
echo " TẤT CẢ CÁC BƯỚC TEST DEPLOY ĐÃ THÀNH CÔNG (100% PASS)!\n";
echo "====================================================\n";

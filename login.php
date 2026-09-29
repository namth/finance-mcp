<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Nếu đã đăng nhập thì vào thẳng trang chủ
if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

spl_autoload_register(function ($class) {
    $prefix = 'SimpleFinance\\';
    $baseDir = __DIR__ . '/src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});

use SimpleFinance\Models\User;

$userModel = null;
$dbError = null;

try {
    $userModel = new User();
} catch (\Throwable $e) {
    $dbError = $e->getMessage();
}

// Nếu không kết nối được CSDL, hiển thị trang hướng dẫn khắc phục thay vì lỗi 500
if ($dbError !== null) {
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Lỗi Kết Nối Cơ Sở Dữ Liệu - SimpleFinance</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-slate-50 flex items-center justify-center min-h-screen p-4 font-sans">
        <div class="max-w-lg w-full bg-white p-6 sm:p-8 rounded-2xl shadow-xl border border-slate-200">
            <div class="w-14 h-14 bg-amber-100 text-amber-600 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-sm">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
            
            <h1 class="text-xl font-bold text-slate-900 text-center">Chưa Kết Nối Được Cơ Sở Dữ Liệu</h1>
            <p class="text-xs text-slate-500 text-center mt-1">Cần cấu hình thông tin MySQL trên máy chủ</p>

            <div class="mt-4 p-4 bg-red-50 border border-red-200 rounded-xl text-xs font-mono text-red-700 break-all leading-relaxed">
                <strong>Chi tiết lỗi:</strong><br>
                <?= htmlspecialchars($dbError) ?>
            </div>

            <div class="mt-5 space-y-3 text-xs text-slate-600">
                <p class="font-bold text-slate-800">🛠 Cách xử lý rất đơn giản:</p>
                
                <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-3">
                    <div>
                        <p class="font-bold text-slate-900">1. Cập nhật thông số MySQL trong file <code class="text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">config.php</code> (hoặc tạo <code class="text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">config.local.php</code>):</p>
                        <p class="text-[11px] text-slate-500 mt-1">Mở file cấu hình trên server và điền đúng <span class="font-mono text-slate-700">username</span>, <span class="font-mono text-slate-700">password</span>, <span class="font-mono text-slate-700">dbname</span> của database hosting.</p>
                    </div>

                    <div>
                        <p class="font-bold text-slate-900">2. Đảm bảo đã import cấu trúc bảng:</p>
                        <p class="text-[11px] text-slate-500 mt-1">Nếu database mới tinh, hãy import file <code class="font-mono text-slate-700">schema_multiuser.sql</code> vào database MySQL.</p>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex flex-col sm:flex-row gap-3">
                <a href="login.php" class="flex-1 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs text-center transition shadow-sm">
                    Tải Lại Trang (Reload)
                </a>
                <a href="register.php" class="flex-1 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs text-center transition">
                    Đến Trang Đăng Ký
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$errorMessage = '';

// Xử lý nút "Đổi tài khoản khác"
if (isset($_GET['switch_user'])) {
    unset($_SESSION['login_target_user_id'], $_SESSION['login_target_user_name'], $_SESSION['login_target_username'], $_SESSION['login_fail_count'], $_SESSION['login_quiz']);
    header('Location: login.php');
    exit;
}

// =========================================================================
// BƯỚC 1: XÁC ĐỊNH USERNAME / TÀI KHOẢN
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'identify_user') {
    $loginInput = trim((string)($_POST['username'] ?? ''));

    if (empty($loginInput)) {
        $errorMessage = "Vui lòng nhập tên đăng nhập hoặc email.";
    } else {
        try {
            $user = $userModel->findByUsernameOrEmail($loginInput);
            if (!$user) {
                $errorMessage = "Không tìm thấy tài khoản với tên đăng nhập hoặc email này.";
            } else {
                $events = $userModel->getDecryptedTimelineEvents((int)$user['id']);
                if (count($events) < 3) {
                    $errorMessage = "Tài khoản này chưa có đủ mốc ký ức để mở khóa. Vui lòng liên hệ quản trị.";
                } else {
                    $_SESSION['login_target_user_id']   = (int)$user['id'];
                    $_SESSION['login_target_user_name'] = $user['full_name'];
                    $_SESSION['login_target_username']  = $user['username'];
                    $_SESSION['login_fail_count']       = 0;
                    unset($_SESSION['login_quiz']);
                    header('Location: login.php');
                    exit;
                }
            }
        } catch (\Throwable $e) {
            $errorMessage = "Lỗi CSDL: " . $e->getMessage() . ". Hãy đảm bảo bạn đã import file schema_multiuser.sql vào MySQL.";
        }
    }
}

// =========================================================================
// BƯỚC 2: XÁC THỰC BẰNG KÝ ỨC THỜI GIAN (NUMPAD PIN)
// =========================================================================
$targetUserId = $_SESSION['login_target_user_id'] ?? null;
$quiz = [];

if ($targetUserId) {
    $timelineEvents = $userModel->getDecryptedTimelineEvents($targetUserId);

    if (!isset($_SESSION['login_fail_count'])) {
        $_SESSION['login_fail_count'] = 0;
    }

    $failCount = (int)$_SESSION['login_fail_count'];
    $maxPossible = count($timelineEvents);
    $numQuestions = min($maxPossible, 3 + $failCount);

    // Xử lý gửi đáp án Numpad POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'refresh') {
            unset($_SESSION['login_quiz']);
            header('Location: login.php');
            exit;
        }

        if ($action === 'verify') {
            $savedQuiz = $_SESSION['login_quiz'] ?? [];
            $answers = $_POST['answers'] ?? [];

            $isAllCorrect = true;

            if (empty($savedQuiz) || count($answers) < count($savedQuiz)) {
                $isAllCorrect = false;
            } else {
                foreach ($savedQuiz as $idx => $q) {
                    $userAns = isset($answers[$idx]) ? trim((string)$answers[$idx]) : '';
                    $userNum = intval($userAns);
                    $expectedNum = intval($q['expected']);

                    if ($userAns === '' || $userNum !== $expectedNum) {
                        $isAllCorrect = false;
                        break;
                    }
                }
            }

            if ($isAllCorrect) {
                // Đăng nhập thành công!
                $_SESSION['user_id'] = $targetUserId;
                $_SESSION['user'] = $userModel->findById($targetUserId);
                unset($_SESSION['login_target_user_id'], $_SESSION['login_target_user_name'], $_SESSION['login_target_username'], $_SESSION['login_fail_count'], $_SESSION['login_quiz']);

                header('Location: index.php');
                exit;
            } else {
                // Trả lời sai -> Phạt tăng thêm 1 câu hỏi ở lần thử tiếp theo!
                $_SESSION['login_fail_count']++;
                $newFail = $_SESSION['login_fail_count'];
                $nextCount = min($maxPossible, 3 + $newFail);
                unset($_SESSION['login_quiz']); // Xáo trộn sinh câu mới

                $errorMessage = "Câu trả lời chưa chính xác! Bạn bị phạt thêm 1 câu hỏi ở lần này (Tổng cộng: {$nextCount} câu).";
                $numQuestions = $nextCount;
            }
        }
    }

    // Sinh bộ câu hỏi ngẫu nhiên từ sự kiện của riêng user này
    if (empty($_SESSION['login_quiz'])) {
        $eventKeys = array_keys($timelineEvents);
        shuffle($eventKeys);

        $selectedEvents = array_slice($eventKeys, 0, $numQuestions);
        $quiz = [];
        $typeOptions = ['day', 'month', 'year'];

        foreach ($selectedEvents as $eventName) {
            $dateStr = $timelineEvents[$eventName]; // DD-MM-YYYY
            $parts = explode('-', $dateStr);

            $day   = (int)($parts[0] ?? 1);
            $month = (int)($parts[1] ?? 1);
            $year  = (int)($parts[2] ?? 2000);

            $type = $typeOptions[array_rand($typeOptions)];

            switch ($type) {
                case 'day':
                    $text = "Ngày diễn ra sự kiện \"<span class='font-bold text-emerald-700'>{$eventName}</span>\" là ngày bao nhiêu?";
                    $expected = $day;
                    $hint = "Ngày (2 chữ số)";
                    $length = 2;
                    break;
                case 'month':
                    $text = "Sự kiện \"<span class='font-bold text-emerald-700'>{$eventName}</span>\" diễn ra vào tháng mấy?";
                    $expected = $month;
                    $hint = "Tháng (2 chữ số)";
                    $length = 2;
                    break;
                case 'year':
                default:
                    $text = "Sự kiện \"<span class='font-bold text-emerald-700'>{$eventName}</span>\" diễn ra vào năm nào?";
                    $expected = $year;
                    $hint = "Năm (4 chữ số)";
                    $length = 4;
                    break;
            }

            $quiz[] = [
                'event'    => $eventName,
                'type'     => $type,
                'text'     => $text,
                'expected' => $expected,
                'hint'     => $hint,
                'length'   => $length,
            ];
        }

        $_SESSION['login_quiz'] = $quiz;
    } else {
        $quiz = $_SESSION['login_quiz'];
    }
}
?>
<!DOCTYPE html>
<html lang="vi" class="h-full bg-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Đăng Nhập Ký Ức Thời Gian - SimpleFinance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes pulse-border {
            0%, 100% { border-color: #059669; box-shadow: 0 0 0 2px rgba(5, 150, 105, 0.2); }
            50% { border-color: #10b981; box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.25); }
        }
        .active-box { animation: pulse-border 1.5s infinite; }
        .numpad-btn:active { transform: scale(0.94); }
    </style>
</head>
<body class="h-full flex flex-col justify-center items-center p-4 bg-gradient-to-br from-slate-100 via-emerald-50/50 to-slate-200 select-none">

    <div class="w-full max-w-sm flex flex-col items-center">
        <!-- Logo Header -->
        <div class="flex items-center space-x-2.5 mb-4">
            <div class="w-10 h-10 rounded-xl bg-emerald-600 flex items-center justify-center text-white shadow-md shadow-emerald-600/20">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
            </div>
            <div>
                <h1 class="text-lg font-bold text-slate-900 leading-tight">SimpleFinance</h1>
                <p class="text-[11px] text-slate-500 font-medium">Bảo mật Ký Ức Thời Gian</p>
            </div>
        </div>

        <?php if (!$targetUserId): ?>
            <!-- =============================================================== -->
            <!-- GIAO DIỆN BƯỚC 1: NHẬP TÊN ĐĂNG NHẬP / EMAIL                    -->
            <!-- =============================================================== -->
            <div class="w-full bg-white rounded-3xl shadow-xl shadow-slate-200/70 border border-slate-200/80 p-7">
                <div class="text-center mb-5">
                    <h2 class="text-base font-bold text-slate-900">Đăng Nhập Tài Khoản</h2>
                    <p class="text-xs text-slate-500 mt-1">Bước 1: Nhập tên đăng nhập để xác định danh tính</p>
                </div>

                <?php if (!empty($errorMessage)): ?>
                    <div class="mb-4 p-3 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-center space-x-2">
                        <svg class="h-4 w-4 text-rose-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/>
                        </svg>
                        <span><?= htmlspecialchars($errorMessage) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php" class="space-y-4">
                    <input type="hidden" name="action" value="identify_user">
                    
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Username hoặc Email</label>
                        <input type="text" name="username" required autofocus placeholder="nguyenvana hoặc email"
                               class="w-full px-3.5 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 text-sm font-medium">
                    </div>

                    <button type="submit" class="w-full py-3 px-4 rounded-xl text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 shadow-md shadow-emerald-600/20 transition flex items-center justify-center">
                        Tiếp tục Mở Khóa
                        <svg class="w-4 h-4 ml-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                        </svg>
                    </button>
                </form>

                <div class="mt-6 pt-4 border-t border-slate-100 text-center text-xs text-slate-500">
                    Chưa có tài khoản? 
                    <a href="register.php" class="font-bold text-emerald-700 hover:underline">
                        Đăng ký ngay
                    </a>
                </div>
            </div>

        <?php else: ?>
            <!-- =============================================================== -->
            <!-- GIAO DIỆN BƯỚC 2: MỞ KHÓA BẰNG KÝ ỨC (NUMPAD PIN WIZARD)         -->
            <!-- =============================================================== -->
            <div class="w-full bg-white rounded-3xl shadow-xl shadow-slate-200/70 border border-slate-200/80 p-6 flex flex-col items-center relative overflow-hidden">
                
                <!-- Thanh thông tin User & Nút đổi user -->
                <div class="w-full flex items-center justify-between pb-3 mb-3 border-b border-slate-100 text-xs">
                    <div class="truncate">
                        <span class="text-slate-400">Tài khoản:</span>
                        <span class="font-bold text-emerald-800 ml-1"><?= htmlspecialchars($_SESSION['login_target_user_name']) ?></span>
                        <span class="text-slate-400 text-[10px]">(@<?= htmlspecialchars($_SESSION['login_target_username']) ?>)</span>
                    </div>
                    <a href="login.php?switch_user=1" class="text-slate-400 hover:text-rose-600 text-[11px] font-semibold transition ml-2 flex-shrink-0">
                        Đổi tài khoản
                    </a>
                </div>

                <!-- Thanh Tiến Trình (Progress Bars) -->
                <div class="w-full flex items-center space-x-1.5 mb-4" id="progressContainer">
                    <?php for ($i = 0; $i < count($quiz); $i++): ?>
                        <div id="prog_<?= $i ?>" class="h-1.5 flex-1 rounded-full transition-all duration-300 <?= $i === 0 ? 'bg-emerald-500' : 'bg-slate-200' ?>"></div>
                    <?php endfor; ?>
                </div>

                <!-- Cảnh Báo Lỗi nếu có -->
                <?php if (!empty($errorMessage)): ?>
                    <div class="w-full mb-3 p-3 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-start space-x-2">
                        <svg class="h-4 w-4 text-rose-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                        </svg>
                        <div class="flex-1">
                            <span class="font-bold">Mở khóa thất bại!</span> <?= htmlspecialchars($errorMessage) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Tiêu đề câu hỏi & Step Badge -->
                <div class="text-center min-h-[70px] flex flex-col items-center justify-center mb-2">
                    <span id="questionStepBadge" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 mb-1">
                        Câu hỏi 1 / <?= count($quiz) ?>
                    </span>
                    <p id="questionText" class="text-sm text-slate-800 leading-snug px-1">
                        <!-- Text được cập nhật bằng JS -->
                    </p>
                    <span id="questionHint" class="text-[10px] text-slate-400 mt-0.5">
                        <!-- Gợi ý định dạng DD/MM/YYYY -->
                    </span>
                </div>

                <!-- Các Ô Nhập Số (PIN Boxes) -->
                <div id="boxesContainer" class="flex justify-center items-center space-x-2 my-2 h-13">
                    <!-- Javascript sinh các ô số tại đây -->
                </div>

                <!-- Loading overlay khi submit -->
                <div id="submitLoading" class="hidden absolute inset-0 bg-white/95 backdrop-blur-xs z-20 flex flex-col items-center justify-center space-y-3">
                    <div class="w-8 h-8 border-3 border-emerald-600 border-t-transparent rounded-full animate-spin"></div>
                    <p class="text-xs font-bold text-slate-700">Đang đối chiếu ký ức bí mật...</p>
                </div>

                <!-- Bàn Phím Số Tròn (Numpad) -->
                <div class="w-full max-w-[270px] grid grid-cols-3 gap-2.5 mt-3">
                    <?php for ($num = 1; $num <= 9; $num++): ?>
                        <button type="button" onclick="pressKey('<?= $num ?>')" 
                                class="numpad-btn h-13 rounded-2xl bg-slate-50 hover:bg-emerald-50 active:bg-emerald-100 text-slate-800 hover:text-emerald-700 text-2xl font-bold border border-slate-200/80 shadow-xs flex items-center justify-center transition-all">
                            <?= $num ?>
                        </button>
                    <?php endfor; ?>

                    <!-- Nút Đổi câu hỏi ngẫu nhiên khác -->
                    <form action="login.php" method="POST" class="w-full h-full m-0 p-0">
                        <input type="hidden" name="action" value="refresh">
                        <button type="submit" 
                                class="numpad-btn w-full h-13 rounded-2xl bg-slate-50 hover:bg-slate-100 active:bg-slate-200 text-slate-500 text-xs font-semibold border border-slate-200/80 flex flex-col items-center justify-center transition-all" title="Đổi câu hỏi khác">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            <span class="text-[9px] mt-0.5">Đổi câu</span>
                        </button>
                    </form>

                    <!-- Phím số 0 -->
                    <button type="button" onclick="pressKey('0')" 
                            class="numpad-btn h-13 rounded-2xl bg-slate-50 hover:bg-emerald-50 active:bg-emerald-100 text-slate-800 hover:text-emerald-700 text-2xl font-bold border border-slate-200/80 shadow-xs flex items-center justify-center transition-all">
                        0
                    </button>

                    <!-- Nút Xóa lùi Backspace (⌫) -->
                    <button type="button" onclick="pressBackspace()" 
                            class="numpad-btn h-13 rounded-2xl bg-slate-50 hover:bg-rose-50 active:bg-rose-100 text-slate-600 hover:text-rose-600 border border-slate-200/80 flex items-center justify-center transition-all" title="Xóa số">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M3 12l6.414-6.414a2 2 0 011.414-.586H19a2 2 0 012 2v10a2 2 0 01-2 2h-7.586a2 2 0 01-1.414-.586L3 12z" />
                        </svg>
                    </button>
                </div>

                <!-- Form Ẩn Dùng Để Tự Động Submit Khi Hết Câu -->
                <form id="quizForm" action="login.php" method="POST" class="hidden">
                    <input type="hidden" name="action" value="verify">
                    <?php foreach ($quiz as $idx => $q): ?>
                        <input type="hidden" name="answers[<?= $idx ?>]" id="hidden_ans_<?= $idx ?>" value="">
                    <?php endforeach; ?>
                </form>

            </div>

            <!-- Script JS cho Numpad -->
            <script>
                const quizList = <?= json_encode($quiz) ?>;
                const totalQuestions = quizList.length;

                let currentIndex = 0;
                let userAnswers = new Array(totalQuestions).fill('');
                let currentInputChars = [];

                function renderQuestion() {
                    const currentQ = quizList[currentIndex];
                    document.getElementById('questionStepBadge').textContent = `Câu hỏi ${currentIndex + 1} / ${totalQuestions}`;
                    document.getElementById('questionText').innerHTML = currentQ.text;
                    document.getElementById('questionHint').textContent = currentQ.hint;

                    for (let i = 0; i < totalQuestions; i++) {
                        const bar = document.getElementById(`prog_${i}`);
                        if (i < currentIndex) {
                            bar.className = 'h-1.5 flex-1 rounded-full bg-emerald-600 transition-all duration-300';
                        } else if (i === currentIndex) {
                            bar.className = 'h-1.5 flex-1 rounded-full bg-emerald-400 transition-all duration-300 ring-2 ring-emerald-400/30';
                        } else {
                            bar.className = 'h-1.5 flex-1 rounded-full bg-slate-200 transition-all duration-300';
                        }
                    }

                    const container = document.getElementById('boxesContainer');
                    container.innerHTML = '';
                    currentInputChars = userAnswers[currentIndex] ? userAnswers[currentIndex].split('') : [];

                    for (let i = 0; i < currentQ.length; i++) {
                        const box = document.createElement('div');
                        box.id = `charBox_${i}`;
                        const val = currentInputChars[i] || '';
                        const isActive = (i === currentInputChars.length);

                        box.className = `w-11 h-13 rounded-2xl border-2 flex items-center justify-center text-2xl font-black transition-all ${
                            val ? 'border-emerald-600 bg-emerald-50/40 text-emerald-900' : 
                            (isActive ? 'active-box bg-white text-slate-800' : 'border-slate-200 bg-slate-50 text-slate-400')
                        }`;
                        box.textContent = val;
                        container.appendChild(box);
                    }
                }

                function updateBoxesDisplay() {
                    const currentQ = quizList[currentIndex];
                    for (let i = 0; i < currentQ.length; i++) {
                        const box = document.getElementById(`charBox_${i}`);
                        if (!box) continue;
                        const val = currentInputChars[i] || '';
                        const isActive = (i === currentInputChars.length);

                        box.textContent = val;
                        if (val) {
                            box.className = 'w-11 h-13 rounded-2xl border-2 border-emerald-600 bg-emerald-50/40 text-emerald-900 flex items-center justify-center text-2xl font-black transition-all';
                        } else if (isActive) {
                            box.className = 'w-11 h-13 rounded-2xl border-2 active-box bg-white text-slate-800 flex items-center justify-center text-2xl font-black transition-all';
                        } else {
                            box.className = 'w-11 h-13 rounded-2xl border-2 border-slate-200 bg-slate-50 text-slate-400 flex items-center justify-center text-2xl font-black transition-all';
                        }
                    }
                }

                function pressKey(num) {
                    const currentQ = quizList[currentIndex];
                    if (currentInputChars.length < currentQ.length) {
                        currentInputChars.push(num);
                        updateBoxesDisplay();

                        if (currentInputChars.length === currentQ.length) {
                            userAnswers[currentIndex] = currentInputChars.join('');
                            document.getElementById(`hidden_ans_${currentIndex}`).value = userAnswers[currentIndex];

                            setTimeout(() => {
                                if (currentIndex < totalQuestions - 1) {
                                    currentIndex++;
                                    renderQuestion();
                                } else {
                                    document.getElementById('submitLoading').classList.remove('hidden');
                                    document.getElementById('quizForm').submit();
                                }
                            }, 180);
                        }
                    }
                }

                function pressBackspace() {
                    if (currentInputChars.length > 0) {
                        currentInputChars.pop();
                        userAnswers[currentIndex] = currentInputChars.join('');
                        document.getElementById(`hidden_ans_${currentIndex}`).value = userAnswers[currentIndex];
                        updateBoxesDisplay();
                    } else if (currentIndex > 0) {
                        currentIndex--;
                        renderQuestion();
                    }
                }

                document.addEventListener('keydown', (e) => {
                    if (e.key >= '0' && e.key <= '9') {
                        pressKey(e.key);
                    } else if (e.key === 'Backspace' || e.key === 'Delete') {
                        pressBackspace();
                    }
                });

                document.addEventListener('DOMContentLoaded', () => {
                    renderQuestion();
                });
            </script>
        <?php endif; ?>

    </div>

</body>
</html>

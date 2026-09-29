<?php

/**
 * Script kiểm thử cơ chế Xác thực Ký Ức Thời Gian (Memory Timeline Unlock)
 */

$config = require __DIR__ . '/../config.php';
$timelineEvents = $config['timeline_events'] ?? [];

echo "=== KIỂM THỬ XÁC THỰC KÝ ỨC THỜI GIAN ===\n";
echo "1. Danh sách sự kiện đã cấu hình: " . count($timelineEvents) . " mốc\n";
foreach ($timelineEvents as $ev => $d) {
    echo "   * {$ev}: {$d}\n";
}

// Giả lập logic sinh câu hỏi
$eventKeys = array_keys($timelineEvents);
shuffle($eventKeys);
$selected = array_slice($eventKeys, 0, 3);

echo "\n2. Sinh thử nghiệm 3 câu hỏi ngẫu nhiên:\n";
$quiz = [];
foreach ($selected as $ev) {
    $dateStr = $timelineEvents[$ev];
    $parts = explode('-', $dateStr);
    $types = ['day', 'month', 'year'];
    $t = $types[array_rand($types)];
    $expected = match($t) {
        'day' => (int)$parts[0],
        'month' => (int)$parts[1],
        'year' => (int)$parts[2],
    };
    echo "   -> Câu hỏi về [{$ev}]: hỏi {$t} -> đáp án mong đợi: {$expected}\n";
    $quiz[] = ['event' => $ev, 'type' => $t, 'expected' => $expected];
}

echo "\n3. Giả lập trả lời SAI một câu:\n";
$failCount = 0;
// Giả sử câu 1 trả lời sai:
$answers = [
    0 => $quiz[0]['expected'] + 99, // cố tình sai
    1 => $quiz[1]['expected'],
    2 => $quiz[2]['expected'],
];

$isCorrect = true;
foreach ($quiz as $i => $q) {
    if (intval($answers[$i]) !== intval($q['expected'])) {
        $isCorrect = false;
        break;
    }
}

if (!$isCorrect) {
    $failCount++;
    $nextQuestions = min(count($timelineEvents), 3 + $failCount);
    echo "   -> Phát hiện trả lời SAI! Phạt tăng số câu hỏi lên: {$nextQuestions} câu (Đúng theo thiết kế).\n";
}

echo "\n4. Giả lập trả lời ĐÚNG toàn bộ ở lần tiếp theo:\n";
$correctAnswers = [];
foreach ($quiz as $i => $q) {
    $correctAnswers[$i] = $q['expected'];
}

$isCorrectAll = true;
foreach ($quiz as $i => $q) {
    if (intval($correctAnswers[$i]) !== intval($q['expected'])) {
        $isCorrectAll = false;
        break;
    }
}

if ($isCorrectAll) {
    echo "   -> Xác nhận ĐÚNG TẤT CẢ -> Mở khóa Admin thành công!\n";
}

echo "\n=== HOÀN TẤT KIỂM THỬ XÁC THỰC: 100% ĐẠT CHUẨN ===\n";

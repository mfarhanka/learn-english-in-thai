<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// --- Same-origin protection -------------------------------------------------
// Allow requests whose Origin or Referer header matches this server's host.
// Requests with neither header (e.g. direct curl calls) are rejected.
$serverHost = $_SERVER['HTTP_HOST'] ?? '';
$origin     = $_SERVER['HTTP_ORIGIN']  ?? '';
$referer    = $_SERVER['HTTP_REFERER'] ?? '';

$originHost  = $origin  ? parse_url($origin,  PHP_URL_HOST) : '';
$refererHost = $referer ? parse_url($referer, PHP_URL_HOST) : '';

if ($originHost !== $serverHost && $refererHost !== $serverHost) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// --- Simple IP-based rate limiting (10 notifications per minute) -----------
session_start();
$now    = time();
$window = 60;
$limit  = 10;

if (!isset($_SESSION['notify_times']) || !is_array($_SESSION['notify_times'])) {
    $_SESSION['notify_times'] = [];
}
// Remove timestamps outside the current window
$_SESSION['notify_times'] = array_filter(
    $_SESSION['notify_times'],
    fn($t) => ($now - $t) < $window
);

if (count($_SESSION['notify_times']) >= $limit) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many requests']);
    exit;
}
$_SESSION['notify_times'][] = $now;
// ---------------------------------------------------------------------------

$configFile = '../config.php';
if (file_exists($configFile)) {
    require_once $configFile;
}

$botToken = defined('TELEGRAM_BOT_TOKEN') ? TELEGRAM_BOT_TOKEN : '';
$chatId   = defined('TELEGRAM_CHAT_ID')   ? TELEGRAM_CHAT_ID   : '';

if (empty($botToken) || empty($chatId)) {
    echo json_encode(['success' => false, 'error' => 'Telegram not configured']);
    exit;
}

$input = file_get_contents('php://input');
$data  = json_decode($input, true);

if (!isset($data['thai_word'], $data['english_word'], $data['result'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$thaiWord    = htmlspecialchars($data['thai_word'],    ENT_QUOTES, 'UTF-8');
$englishWord = htmlspecialchars($data['english_word'], ENT_QUOTES, 'UTF-8');
$result      = $data['result']; // 'correct' | 'incorrect' | 'revealed'
$score       = isset($data['score']) ? intval($data['score']) : 0;
$userAnswer  = isset($data['user_answer']) ? htmlspecialchars($data['user_answer'], ENT_QUOTES, 'UTF-8') : '';

$resultEmoji = [
    'correct'   => '✅',
    'incorrect' => '❌',
    'revealed'  => '💡',
];
$emoji = isset($resultEmoji[$result]) ? $resultEmoji[$result] : '❓';

$resultLabels = [
    'correct'   => 'Correct',
    'incorrect' => 'Incorrect',
    'revealed'  => 'Answer Revealed',
];
$label = isset($resultLabels[$result]) ? $resultLabels[$result] : $result;

$lines = [
    "{$emoji} *{$label}*",
    "",
    "🇹🇭 Thai word: *{$thaiWord}*",
    "🇬🇧 English word: *{$englishWord}*",
];

if ($result === 'incorrect' && $userAnswer !== '') {
    $lines[] = "✏️ User answered: {$userAnswer}";
}

$lines[] = "";
$lines[] = "🏆 Score: *{$score}*";

$message = implode("\n", $lines);

$url     = "https://api.telegram.org/bot{$botToken}/sendMessage";
$payload = [
    'chat_id'    => $chatId,
    'text'       => $message,
    'parse_mode' => 'Markdown',
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 3);

$response   = curl_exec($ch);
$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError  = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode(['success' => false, 'error' => $curlError]);
    exit;
}

$telegramResponse = json_decode($response, true);

if ($httpStatus === 200 && isset($telegramResponse['ok']) && $telegramResponse['ok']) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $telegramResponse['description'] ?? 'Unknown error']);
}

<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Removed Same-origin protection and IP-based rate limiting for testing

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
    "{$emoji} <b>{$label}</b>",
    "",
    "🇹🇭 Thai word: <b>{$thaiWord}</b>",
    "🇬🇧 English word: <b>{$englishWord}</b>",
];

if ($result === 'incorrect' && $userAnswer !== '') {
    $lines[] = "✏️ User answered: {$userAnswer}";
}

$lines[] = "";
$lines[] = "🏆 Score: <b>{$score}</b>";

$message = implode("\n", $lines);

$url     = "https://api.telegram.org/bot{$botToken}/sendMessage";
$payload = [
    'chat_id'    => $chatId,
    'text'       => $message,
    'parse_mode' => 'HTML',
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 3);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response   = curl_exec($ch);
$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError  = curl_error($ch);
curl_close($ch);

if ($curlError) {
    // Log cURL error to a file for debugging
    file_put_contents('../data/telegram_debug.log', date('Y-m-d H:i:s') . " CURL ERROR: $curlError\n", FILE_APPEND);
    echo json_encode(['success' => false, 'error' => $curlError]);
    exit;
}

$telegramResponse = json_decode($response, true);

// Log Telegram API response for debugging
file_put_contents('../data/telegram_debug.log', date('Y-m-d H:i:s') . " RESPONSE: $response\n", FILE_APPEND);

if ($httpStatus === 200 && isset($telegramResponse['ok']) && $telegramResponse['ok']) {
    echo json_encode(['success' => true]);
} else {
    $errorMsg = $telegramResponse['description'] ?? 'Unknown error';
    file_put_contents('../data/telegram_debug.log', date('Y-m-d H:i:s') . " ERROR: $errorMsg\n", FILE_APPEND);
    echo json_encode(['success' => false, 'error' => $errorMsg]);
}

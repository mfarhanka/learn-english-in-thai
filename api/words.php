<?php
header('Content-Type: application/json');

$jsonFile = '../data/words.json';

// Ensure data file exists
if (!file_exists($jsonFile)) {
    if (!is_dir('../data')) {
        mkdir('../data', 0777, true);
    }
    file_put_contents($jsonFile, '[]');
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $data = file_get_contents($jsonFile);
    echo $data;
} elseif ($method === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (isset($data['thai_word']) && isset($data['english_word'])) {
        $words = json_decode(file_get_contents($jsonFile), true);
        if (!is_array($words)) $words = [];
        
        $newWord = [
            'id' => uniqid(),
            'thai_word' => trim($data['thai_word']),
            'english_word' => strtolower(trim($data['english_word'])),
            'created_at' => date('c')
        ];

        $words[] = $newWord;
        file_put_contents($jsonFile, json_encode($words, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        echo json_encode(['success' => true, 'word' => $newWord]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Missing thai_word or english_word']);
    }
} elseif ($method === 'DELETE') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // Provide a fallback in case we pass the id via query parameter
    if (!$data && isset($_GET['id'])) {
        $data = ['id' => $_GET['id']];
    }

    if (isset($data['id'])) {
        $words = json_decode(file_get_contents($jsonFile), true);
        if (!is_array($words)) $words = [];
        
        $newWords = array_values(array_filter($words, function($w) use ($data) {
            return $w['id'] !== $data['id'];
        }));

        if (count($words) !== count($newWords)) {
            file_put_contents($jsonFile, json_encode($newWords, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            echo json_encode(['success' => true]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Word not found']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Missing id']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

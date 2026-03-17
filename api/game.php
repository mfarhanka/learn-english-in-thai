<?php
header('Content-Type: application/json');

$jsonFile = '../data/words.json';

if (!file_exists($jsonFile)) {
    echo json_encode([]);
    exit;
}

$words = json_decode(file_get_contents($jsonFile), true);

if (!is_array($words) || empty($words)) {
    echo json_encode([]);
    exit;
}

// Support fetching a random subset of words
if (isset($_GET['count'])) {
    $count = intval($_GET['count']);
    if ($count > 0 && $count < count($words)) {
        shuffle($words);
        $words = array_slice($words, 0, $count);
    } elseif ($count >= count($words)) {
        shuffle($words);
    }
} else {
    // Optionally randomize by default for the game
    shuffle($words);
}

echo json_encode($words);

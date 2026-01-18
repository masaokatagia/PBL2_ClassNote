<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POSTリクエストのみ許可されています'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
$noteId = isset($payload['noteId']) ? (string)$payload['noteId'] : '';

if ($noteId === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'noteId が必要です'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ユーザーIDは cookie user_id を優先（フロント実装と整合）
$userId = isset($_COOKIE['user_id']) ? (string)$_COOKIE['user_id'] : '';
$userId = preg_replace('/[^a-zA-Z0-9_-]/', '', $userId);

if ($userId === '') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'ログインが必要です'], JSON_UNESCAPED_UNICODE);
    exit;
}

$usersDir = __DIR__ . DIRECTORY_SEPARATOR . 'Users';
if (!is_dir($usersDir)) {
    if (!mkdir($usersDir, 0777, true)) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Users ディレクトリの作成に失敗しました'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$userFile = $usersDir . DIRECTORY_SEPARATOR . $userId . '.json';

if (!file_exists($userFile)) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'ユーザーファイルが見つかりません'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 読み込み（無ければ新規）
$data = [];
$json = file_get_contents($userFile);
$decoded = json_decode($json, true);
if (is_array($decoded)) {
    $data = $decoded;
}

if (!isset($data['post']) || !is_array($data['post'])) $data['post'] = [];
if (!isset($data['fav']) || !is_array($data['fav'])) $data['fav'] = [];

$noteIdStr = (string)$noteId;
$favs = array_map('strval', $data['fav']);

// トグル: 既にあれば削除 / 無ければ追加（重複は作らない）
$idx = array_search($noteIdStr, $favs, true);
if ($idx === false) {
    $favs[] = $noteIdStr;
} else {
    array_splice($favs, $idx, 1);
}

// 並びを安定化（任意）
$data['fav'] = array_values(array_unique($favs));

$written = file_put_contents(
    $userFile,
    json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
    LOCK_EX
);

if ($written === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'ユーザーファイルへの書き込みに失敗しました'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'status' => 'success',
    'message' => 'お気に入りを更新しました',
    'userId' => $userId,
    'fav' => $data['fav'],
    'isFav' => in_array($noteIdStr, $data['fav'], true) // ← これが必要！
], JSON_UNESCAPED_UNICODE);
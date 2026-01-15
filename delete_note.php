<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POSTのみ'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
$noteId = isset($payload['id']) ? (string)$payload['id'] : '';

if ($noteId === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'id が必要です'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ユーザー確認
$userId = $_COOKIE['user_id'] ?? '';
$userId = preg_replace('/[^a-zA-Z0-9_-]/', '', $userId);

if ($userId === '') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'ログインが必要です'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Users/<id>.json 読み込み
$userFile = __DIR__ . "/Users/$userId.json";
if (!file_exists($userFile)) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'ユーザーデータがありません'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userData = json_decode(file_get_contents($userFile), true);
$userPosts = $userData['post'] ?? [];

// 投稿者チェック
if (!in_array($noteId, $userPosts, true)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'あなたの投稿ではありません'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* -------------------------
   notes.json から削除
------------------------- */
$notesFile = __DIR__ . '/notes.json';
$notes = json_decode(file_get_contents($notesFile), true);
$notes = array_filter($notes, fn($n) => $n['id'] !== $noteId);
file_put_contents($notesFile, json_encode(array_values($notes), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

/* -------------------------
   Resources/<id> フォルダ削除
------------------------- */
$dir = __DIR__ . "/Resources/$noteId";
if (is_dir($dir)) {
    foreach (scandir($dir) as $f) {
        if ($f !== '.' && $f !== '..') {
            @unlink("$dir/$f");
        }
    }
    @rmdir($dir);
}

/* -------------------------
   Users/<user>.json の post から削除
------------------------- */
$userData['post'] = array_values(array_filter($userData['post'], fn($p) => $p !== $noteId));
file_put_contents($userFile, json_encode($userData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

echo json_encode(['status' => 'success']);
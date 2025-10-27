<?php
// リクエストがPOSTであることを確認
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'POSTリクエストのみ許可されています']);
    exit;
}

// データを保存するファイル名
$filename = 'notes.json';

function generate_id() {
    try {
        return bin2hex(random_bytes(12)); // 24桁のhex
    } catch (Exception $e) {
        return md5(uniqid('', true));
    }
}

// (1) クライアントから送信されたJSONデータを取得
$input = file_get_contents('php://input');
$newNote = json_decode($input, true);

// JSONとして不正な場合はエラー
if ($newNote === null) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => '無効なJSONデータです']);
    exit;
}

// (2) 既存のデータをファイルから読み込む
$notes = [];
if (file_exists($filename)) {
    $currentData = file_get_contents($filename);
    $notes = json_decode($currentData, true);
    if ($notes === null) {
        // 既存ファイルが壊れている場合は空にする
        $notes = [];
    }
}

// 既存データのマイグレーション: idが無いものに付与
$dirty = false;
foreach ($notes as $i => $note) {
    if (!is_array($note)) continue;
    if (!array_key_exists('id', $note) || empty($note['id'])) {
        $notes[$i]['id'] = generate_id();
        $dirty = true;
    }
}

// (3) 新しいノートにidを付与して配列に追加
if (!array_key_exists('id', $newNote) || empty($newNote['id'])) {
    $newNote['id'] = generate_id();
}
$notes[] = $newNote;

// (4) 配列全体をJSONファイルに書き戻す
// JSON_UNESCAPED_UNICODE: 日本語が \uXXXX のようにエスケープされるのを防ぐ
// JSON_PRETTY_PRINT: 開発用にファイルを読みやすく整形する (本番では不要)
file_put_contents($filename, json_encode($notes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

// (5) クライアントに成功レスポンスを返す
header('Content-Type: application/json');
echo json_encode(['status' => 'success', 'message' => '保存しました', 'id' => $newNote['id']]);
?>

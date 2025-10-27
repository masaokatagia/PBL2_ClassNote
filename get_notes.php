<?php
header('Content-Type: application/json; charset=utf-8');

$filename = 'notes.json';

function generate_id() {
    // 24桁のhex IDを生成（フォルダ名として安全）
    try {
        return bin2hex(random_bytes(12));
    } catch (Exception $e) {
        return md5(uniqid('', true));
    }
}

$notes = [];
$dirty = false;

if (file_exists($filename)) {
    $currentData = file_get_contents($filename);
    $decoded = json_decode($currentData, true);
    if (is_array($decoded)) {
        $notes = $decoded;
    }
}

// 既存データにidが無ければ付与（マイグレーション）
foreach ($notes as $i => $note) {
    if (!is_array($note)) { continue; }
    if (!array_key_exists('id', $note) || empty($note['id'])) {
        $notes[$i]['id'] = generate_id();
        $dirty = true;
    }
}

// マイグレーションが発生した場合はファイルへ書き戻す
if ($dirty) {
    file_put_contents($filename, json_encode($notes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

echo json_encode($notes, JSON_UNESCAPED_UNICODE);

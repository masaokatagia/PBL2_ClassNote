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

// ここから追加: カバー画像(添付画像の1枚目)を付与
$baseDir = __DIR__ . DIRECTORY_SEPARATOR . 'Resources';
$allowed = ['jpg','jpeg','png','gif','webp','bmp','svg'];
$sanitize_id = function($id) {
    return preg_replace('/[^a-zA-Z0-9_-]/', '', $id ?? '');
};

foreach ($notes as $i => $note) {
    if (!is_array($note) || empty($note['id'])) { continue; }
    $safeId = $sanitize_id($note['id']);
    $dir = $baseDir . DIRECTORY_SEPARATOR . $safeId;
    $cover = null;

    if (is_dir($dir)) {
        $files = array_values(array_filter(scandir($dir), function($f) use ($dir, $allowed) {
            if ($f === '.' || $f === '..') return false;
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            return in_array($ext, $allowed, true) && is_file($dir . DIRECTORY_SEPARATOR . $f);
        }));

        if (!empty($files)) {
            // 名前順の先頭をカバーに採用
            sort($files, SORT_NATURAL | SORT_FLAG_CASE);
            $first = $files[0];
            $cover = 'Resources/' . rawurlencode($safeId) . '/' . rawurlencode($first);
        }
    }
    $notes[$i]['cover'] = $cover;
}

echo json_encode($notes, JSON_UNESCAPED_UNICODE);

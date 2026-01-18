<?php
// リクエストがPOSTであることを確認
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'POSTリクエストのみ許可されています']);
    exit;
}

// データを保存するファイル名
$filename = 'notes.json';
// 画像を保存するベースディレクトリ
$baseUploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'Resources';

function generate_id() {
    try {
        return bin2hex(random_bytes(12)); // 24桁のhex
    } catch (Exception $e) {
        return md5(uniqid('', true));
    }
}

// (1) クライアントから送信されたテキストデータを取得
$title = $_POST['title'] ?? '';
$category = $_POST['category'] ?? 'カテゴリ未設定';
$body = $_POST['body'] ?? '';
$createdAt = $_POST['createdAt'] ?? (new DateTime())->format(DateTime::ATOM);

if (empty($title) || empty($body)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'タイトルと本文は必須です']);
    exit;
}

// (2) 既存のデータをファイルから読み込む
$notes = [];
if (file_exists($filename)) {
    $currentData = file_get_contents($filename);
    $notes = json_decode($currentData, true);
    if ($notes === null) {
        $notes = [];
    }
}

// 既存データのマイグレーション
$dirty = false;
foreach ($notes as $i => $note) {
    if (!is_array($note)) continue;
    if (!array_key_exists('id', $note) || empty($note['id'])) {
        $notes[$i]['id'] = generate_id();
        $dirty = true;
    }
}

// (3) 新しいノートのオブジェクトを作成し、先にIDを生成
$newNoteId = generate_id();
$newNote = [
    'id' => $newNoteId,
    'title' => $title,
    'category' => $category,
    'body' => $body,
    'createdAt' => $createdAt
];

// 配列に追加
$notes[] = $newNote;

// (4) 配列全体をJSONファイルに書き戻す
// ▼▼▼ [変更] 書き込みチェックを追加 ▼▼▼
$bytesWritten = file_put_contents($filename, json_encode($notes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

if ($bytesWritten === false) {
    // JSONファイルへの書き込みに失敗した場合、ここで処理を停止する
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'エラー: ' . $filename . ' への書き込みに失敗しました。ファイルのパーミッション（属性）を 666 に変更してください。'
    ]);
    exit; // これ以降の画像処理は実行しない
}
// ▲▲▲ [変更] ここまで ▲▲▲
$user_id = $_COOKIE['user_id'] ?? null;
if ($user_id) {
    $userFile = __DIR__ . "/Users/{$user_id}.json";
    $userData = [];

    if (file_exists($userFile)) {
        $userData = json_decode(file_get_contents($userFile), true);
    }
    if (!is_array($userData)) {
        $userData = ["passwd" => "", "post" => [], "fav" => []];
    }

    // 新しい投稿IDをユーザーのpost配列に追加
    $userData['post'][] = $newNoteId;

    file_put_contents($userFile, json_encode($userData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}



// (5) [新機能] 画像アップロード処理
$uploadErrors = [];
$uploadedFilesCount = 0;

// 'Resources' ディレクトリが存在しなければ作成 (パーミッションを0777に設定)
if (!is_dir($baseUploadDir)) {
    if (!mkdir($baseUploadDir, 0777, true)) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'エラー: ' . $baseUploadDir . ' ディレクトリの作成に失敗しました。']);
        exit;
    }
}

// 'Resources' が存在しても書き込めない場合
if (!is_writable($baseUploadDir)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'エラー: ' . $baseUploadDir . ' ディレクトリに書き込み権限がありません (777にしてください)。']);
    exit;
}

// ノートID用のディレクトリを作成 (例: Resources/abcdef123456)
$targetDir = $baseUploadDir . DIRECTORY_SEPARATOR . $newNoteId;
if (!is_dir($targetDir)) {
    if (!mkdir($targetDir, 0777, true)) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'エラー: ' . $targetDir . ' の作成に失敗しました。']);
        exit;
    }
    @chmod($targetDir, 0777);
}

// ノートIDディレクトリが書き込めない場合
if (!is_writable($targetDir)) {
    http_response_code(500);
     echo json_encode(['status' => 'error', 'message' => 'エラー: ' . $targetDir . ' に書き込み権限がありません。']);
     exit;
}

// gallery.php で許可されている拡張子リスト
$allowedExtensions = ['jpg','jpeg','png','gif','webp','bmp','svg'];

if (isset($_FILES['images'])) {
    $files = $_FILES['images'];

    for ($i = 0; $i < count($files['name']); $i++) {
        $tmpName = $files['tmp_name'][$i];
        
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            if ($files['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                 $uploadErrors[] = "ファイル " . htmlspecialchars($files['name'][$i]) . " のアップロードエラー (コード: " . $files['error'][$i] . ")";
            }
            continue;
        }

        $originalName = $files['name'][$i];
        $safeName = basename($originalName); 
        $ext = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExtensions, true)) {
            $uploadErrors[] = "ファイル " . htmlspecialchars($safeName) . " は許可されていない形式です。";
            continue;
        }

        $destination = $targetDir . DIRECTORY_SEPARATOR . $safeName;
        
        if (move_uploaded_file($tmpName, $destination)) {
            $uploadedFilesCount++;
        } else {
            $uploadErrors[] = "ファイル " . htmlspecialchars($safeName) . " の移動に失敗しました。";
        }
    }
}

// (6) クライアントに成功レスポンスを返す
header('Content-Type: application/json; charset=utf-8');
$message = '保存しました。';
if ($uploadedFilesCount > 0) {
    $message .= ' 画像 ' . $uploadedFilesCount . ' 件をアップロードしました。';
}
if (!empty($uploadErrors)) {
    $message .= ' いくつかの画像エラーがあります: ' . implode(', ', $uploadErrors);
}

echo json_encode([
    'status' => (empty($uploadErrors) || $uploadedFilesCount > 0) ? 'success' : 'error', 
    'message' => $message, 
    'id' => $newNoteId
]);
?>
<?php
session_start();

// 簡易ログインデモ（本番ではパスワードのハッシュ化やDB利用を推奨）
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['user'] ?? '';
    $pass = $_POST['pass'] ?? '';

    // Users/<user>.json を参照して認証（構成: { passwd: "...", post: [], fav: [] }）
    $safeUser = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$user);
    if ($safeUser === '') {
        $message = 'ログインIDが不正です';
    } else {
        $userDir  = __DIR__ . DIRECTORY_SEPARATOR . 'Users';
        $userFile = $userDir . DIRECTORY_SEPARATOR . $safeUser . '.json';

        // ★ 追加：Users ディレクトリがなければ作成
        if (!is_dir($userDir)) {
            mkdir($userDir, 0755, true);
        }

        /* ===== ユーザーが存在しない場合：新規登録 ===== */
        if (!file_exists($userFile)) {
            $newUser = [
                'passwd' => (string)$pass,
                'post'   => [],
                'fav'    => []
            ];

            if (file_put_contents(
                $userFile,
                json_encode($newUser, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            ) === false) {
                $message = 'ユーザー登録に失敗しました';
            } else {
                $_SESSION['user'] = $safeUser;

                // フロント側(scripts.js)が参照する user_id cookie をセット
                setcookie('user_id', $safeUser, [
                    'expires' => time() + 60 * 60 * 24 * 30,
                    'path' => '/',
                    'samesite' => 'Lax'
                ]);

                header('Location: index.html');
                exit;
            }

        /* ===== ユーザーが存在する場合：従来どおりログイン ===== */
        } else {
            $json = file_get_contents($userFile);
            if ($json === false) {
                $message = 'ユーザーファイルを読み取れません（権限/パスを確認してください）';
            } else {
                $data = json_decode($json, true);
                if (!is_array($data)) {
                    $err = function_exists('json_last_error_msg') ? json_last_error_msg() : 'JSONの形式が不正です';
                    $message = 'ユーザーファイルのJSON形式が不正です: ' . $err;
                } else {
                    $stored = array_key_exists('passwd', $data) ? (string)$data['passwd'] : null;

                    if ($stored !== null && hash_equals($stored, (string)$pass)) {
                        $_SESSION['user'] = $safeUser;

                        setcookie('user_id', $safeUser, [
                            'expires' => time() + 60 * 60 * 24 * 30,
                            'path' => '/',
                            'samesite' => 'Lax'
                        ]);

                        header('Location: index.html');
                        exit;
                    }
                    $message = 'ログインIDまたはパスワードが違います';
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ログインページ - Class Note</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <main class="container">
    <h1>ログイン / 新規登録</h1>
    <?php if ($message): ?>
      <div class="card" role="alert" style="color:red">
        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>
    <form method="post" class="card" style="max-width:420px">
      <label>ログインID<input name="user" required></label>
      <label>パスワード<input type="password" name="pass" required></label>
      <button class="btn primary" type="submit">ログイン</button>
      <a class="btn" href="index.html">戻る</a>
    </form>
    <p style="font-size:0.9em;color:#666;margin-top:1em">
      ※ 未登録のIDは自動的に新規登録されます
    </p>
  </main>
</body>
</html>

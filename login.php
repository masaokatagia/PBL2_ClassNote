<?php
session_start();

// �ȈՃ��O�C���f���i���^�p�ł̓p�X���[�h���n�b�V�������ADB�����g�p���Ă��������j
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['user'] ?? '';
    $pass = $_POST['pass'] ?? '';
    if ($user === 'demo' && $pass === 'pass') {
        $_SESSION['user'] = $user;
        header('Location: /web/index.html');
        exit;
    }
    $message = 'ログインIDまたはパスワードが違います;
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
    <h1>ログイン</h1>
    <?php if ($message): ?>
      <div class="card" role="alert"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <form method="post" class="card" style="max-width:420px">
      <label>ログインID<input name="user" required></label>
      <label>パスワードh<input type="password" name="pass" required></label>
      <button class="btn primary" type="submit">ログイン</button>
      <a class="btn" href="index.html">戻る</a>
    </form>
  </main>
</body>
</html>

<?php
// gallery.php?id={id}

function sanitize_id($id) {
    // フォルダ名として安全な文字だけ許可（英数とハイフン/アンダースコア）
    $id = preg_replace('/[^a-zA-Z0-9_-]/', '', $id ?? '');
    return $id;
}

$id = isset($_GET['id']) ? sanitize_id($_GET['id']) : '';
$baseDir = __DIR__ . DIRECTORY_SEPARATOR . 'Resources';
$targetDir = $baseDir . DIRECTORY_SEPARATOR . $id;

$images = [];
if ($id && is_dir($targetDir)) {
    $allowed = ['jpg','jpeg','png','gif','webp','bmp','svg'];
    foreach (scandir($targetDir) as $file) {
        if ($file === '.' || $file === '..') continue;
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, $allowed, true)) {
            $images[] = 'Resources/' . rawurlencode($id) . '/' . rawurlencode($file);
        }
    }
}
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>ギャラリー - Class Note</title>
  <link rel="stylesheet" href="styles.css" />
  <style>
    .gallery-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;margin-top:16px}
    .gallery-grid img{width:100%;height:160px;object-fit:cover;border-radius:8px;border:1px solid var(--border);background:#fff}
    .muted{color:var(--muted)}
  </style>
</head>
<body>
  <header class="header">
    <h1>ギャラリー</h1>
    <nav class="actions">
      <a class="btn" href="index.html">戻る</a>
    </nav>
  </header>
  <main class="container">
    <?php if (!$id): ?>
      <p class="muted">IDが指定されていません。</p>
    <?php elseif (!is_dir($targetDir)): ?>
      <p class="muted">Resources/<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?> は存在しません。</p>
    <?php elseif (empty($images)): ?>
      <p class="muted">画像がありません。</p>
    <?php else: ?>
      <h2>Resources/<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?></h2>
      <section class="gallery-grid">
        <?php foreach ($images as $src): ?>
          <img src="<?php echo $src; ?>" alt="image" loading="lazy" />
        <?php endforeach; ?>
      </section>
    <?php endif; ?>
  </main>
  <footer class="footer">
    <small>&copy; 2025 Class Note</small>
  </footer>
</body>
</html>

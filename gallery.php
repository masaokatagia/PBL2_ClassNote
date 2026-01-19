<?php
function sanitize_id($id)
{
  return preg_replace('/[^a-zA-Z0-9_-]/', '', $id ?? '');
}

$id = isset($_GET['id']) ? sanitize_id($_GET['id']) : '';
$baseDir = __DIR__ . '/Resources';
$targetDir = $baseDir . '/' . $id;

$notes = json_decode(file_get_contents(__DIR__ . '/notes.json'), true);
$note = null;
foreach ($notes as $n) {
  if ($n['id'] === $id) {
    $note = $n;
    break;
  }
}

$images = [];
if ($id && is_dir($targetDir)) {
  $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
  foreach (scandir($targetDir) as $file) {
    if ($file === '.' || $file === '..') continue;
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (in_array($ext, $allowed, true)) {
      $images[] = "Resources/" . rawurlencode($id) . "/" . rawurlencode($file);
    }
  }
}

// ユーザー情報
$userId = $_COOKIE['user_id'] ?? null;
$userFav = [];
$userPost = [];

if ($userId && file_exists(__DIR__ . "/Users/$userId.json")) {
  $u = json_decode(file_get_contents(__DIR__ . "/Users/$userId.json"), true);
  $userFav = $u['fav'] ?? [];
  $userPost = $u['post'] ?? [];
}

$isFav = in_array($id, $userFav);
$isMine = in_array($id, $userPost);
?>
<!doctype html>
<html lang="ja">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($note['title'] ?? '投稿詳細') ?> - Class Note</title>
  <link rel="stylesheet" href="styles.css">

  <style>
    .gallery-grid {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
    }

    .gallery-grid img {
      width: 200px;
      height: 200px;
      object-fit: contain;
      border-radius: 8px;
      border: 1px solid var(--border);
      cursor: zoom-in;
      background: #fff;
    }

    #lightbox {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.85);
      display: none;
      justify-content: center;
      align-items: center;
      z-index: 999;
    }

    #lightbox img {
      max-width: 90%;
      max-height: 90%;
      border-radius: 8px;
    }

    .fav-btn.active {
      background: #fff;
      color: var(--primary);
      border-color: var(--primary);
    }
  </style>
</head>

<body>
  <header class="header">
    <a href="index.html"><label class="btn">
        <h1>Class Note</h1>
      </label></a>
    <nav class="actions">
      <a class="btn" href="post.html">投稿</a>
      <a class="btn js-auth-link" href="login.php">ログイン</a>
    </nav>
  </header>

  <main class="container">

    <div class="card" style="max-width:800px; margin:auto;">

      <?php if (!$note): ?>
        <p>投稿が見つかりません。</p>

      <?php else: ?>

        <div class="card-title-row">
          <h2 style="margin:0;"><?= htmlspecialchars($note['title']) ?></h2>

          <?php if ($userId): ?>
            <button id="favBtn" class="fav-btn <?= $isFav ? 'active' : '' ?>" data-id="<?= htmlspecialchars($id) ?>">
              <?= $isFav ? '★' : '☆' ?>
            </button>
          <?php endif; ?>
        </div>

        <p><strong>カテゴリ:</strong> <?= htmlspecialchars($note['category']) ?></p>
        <p><strong>投稿日:</strong> <?= htmlspecialchars($note['createdAt']) ?></p>

        <?php if ($isMine): ?>
          <button class="btn danger" id="deleteBtn">削除</button>
        <?php endif; ?>

        <hr>

        <p><?= nl2br(htmlspecialchars($note['body'])) ?></p>

        <hr>

        <h3>画像</h3>

        <?php if (empty($images)): ?>
          <p>画像はありません。</p>
        <?php else: ?>
          <div class="gallery-grid">
            <?php foreach ($images as $src): ?>
              <img src="<?= $src ?>" data-src="<?= $src ?>" class="zoom-img">
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

      <?php endif; ?>
      <br>
      <a class="btn" href="index.html">戻る</a>
    </div>

  </main>

  <div id="lightbox">
    <img id="lightboxImg" src="">
  </div>

  <script>
    document.querySelectorAll('.zoom-img').forEach(img => {
      img.addEventListener('click', () => {
        document.getElementById('lightboxImg').src = img.dataset.src;
        document.getElementById('lightbox').style.display = 'flex';
      });
    });
    document.getElementById('lightbox').addEventListener('click', () => {
      document.getElementById('lightbox').style.display = 'none';
    });

    // お気に入り
    const favBtn = document.getElementById('favBtn');
    if (favBtn) {
      favBtn.addEventListener('click', async () => {
        const id = favBtn.dataset.id;
        const res = await fetch('toggle_favorite.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            noteId: id
          })
        });
        const data = await res.json();
        if (data.status === 'success') {
          favBtn.textContent = data.isFav ? '★' : '☆';
          favBtn.classList.toggle('active', data.isFav); 
        }
      });
    }

    // 削除
    const delBtn = document.getElementById('deleteBtn');
    if (delBtn) {
      delBtn.addEventListener('click', async () => {
        if (!confirm('本当に削除しますか？')) return;
        const res = await fetch('delete_note.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            id: "<?= $id ?>"
          })
        });
        const data = await res.json();
        if (data.status === 'success') {
          alert('削除しました');
          location.href = 'index.html';
        }
      });
    }
  </script>
  <script src="auth.js"></script>
</body>

</html>
// サンプルデータはそのまま残します

let currentView = 'all';
let lastSearchResult = [];

// ▼▼ 追加: user_id Cookie と Users/<id>.json の読み込み＋キャッシュ ▼▼
let currentUserId = null;
let userPostIds = new Set();
let userFavIds = new Set();

function getCookie(name) {
  const m = document.cookie.match(new RegExp('(?:^|;\\s*)' + name.replace(/[-[\]{}()*+?.,\\^$|#\s]/g, '\\$&') + '=([^;]*)'));
  return m ? decodeURIComponent(m[1]) : null;
}

async function loadUserData() {
  currentUserId = getCookie('user_id');
  userPostIds = new Set();
  userFavIds = new Set();

  if (!currentUserId) return; // ログインなし等は従来挙動

  try {
    const res = await fetch(`Users/${encodeURIComponent(currentUserId)}.json`, { cache: 'no-store' });
    if (!res.ok) return;
    const data = await res.json();
    const posts = Array.isArray(data?.post) ? data.post : [];
    const favs = Array.isArray(data?.fav) ? data.fav : [];
    userPostIds = new Set(posts.map(String));
    userFavIds = new Set(favs.map(String));
  } catch (e) {
    // 読めなくても落とさない（従来のフラグ判定にフォールバック）
  }
}

// データを読み込む非同期関数
async function loadNotes() {
  let serverNotes = [];
  try {
    // (1) サーバーから get_notes.php を fetch
    const response = await fetch('get_notes.php');
    if (response.ok) {
      serverNotes = await response.json(); // body と cover を含む
    }
  } catch (e) {
    console.warn('notes.json が見つからないか、読み込めません。');
  }
  
  // サーバーのノート + サンプルデータ（後方互換で desc を維持）
  // ▼▼ 追加: sample 未定義でも落ちないようにする ▼▼
  const fallbackSample = (typeof sample !== 'undefined' && Array.isArray(sample)) ? sample : [];
  const allData = [
    ...serverNotes,
    ...fallbackSample
  ];
  return allData;
}

// 取得したデータをHTMLに描画する関数
function render(items) {
  const root = document.getElementById('results');
  root.innerHTML = '';
  
  for (const it of items) {
    const el = document.createElement('article');
    el.className = 'card';
    if (it.id) {
      // リンク付きカード
      el.classList.add('clickable');
      el.dataset.id = it.id;
      const titleRow = document.createElement('div');
      titleRow.className = 'card-title-row';
      const title = document.createElement('h3');
      title.textContent = it.title ?? '';
      titleRow.appendChild(title);

      const favBtn = document.createElement('button');
      favBtn.type = 'button';
      favBtn.className = 'fav-btn';

      const noteIdStr = String(it.id);
      const isFav = userFavIds && userFavIds.has(noteIdStr);
      favBtn.classList.toggle('active', isFav);
      favBtn.textContent = isFav ? '★' : '☆';
      favBtn.setAttribute('aria-label', isFav ? 'お気に入り解除' : 'お気に入りに追加');
      favBtn.addEventListener('click', async (ev) => {
        ev.preventDefault();
        ev.stopPropagation();
        await toggleFavorite(noteIdStr);
      });
      titleRow.appendChild(favBtn);

      const link = document.createElement('a');
      link.href = `gallery.php?id=${encodeURIComponent(it.id)}`;
      link.className = 'card-link';

      if (it.cover) {
        const img = document.createElement('img');
        img.className = 'thumb';
        img.src = it.cover;
        img.alt = '';
        link.appendChild(img);
      }

      const pCat = document.createElement('p');
      pCat.textContent = it.category ?? '';
      link.appendChild(pCat);

      const pBody = document.createElement('p');
      pBody.textContent = (it.body ?? (it.desc ?? ''));
      link.appendChild(pBody);

      el.appendChild(titleRow);
      el.appendChild(link);
    } else {
      // サンプル等（非リンク）
      el.innerHTML = `
        ${it.cover ? `<img class="thumb" src="${it.cover}" alt="">` : ''}
        <h3>${it.title}</h3>
        <p>${it.category}</p>
        <p>${it.desc ?? ''}</p>
      `;
    }
    root.appendChild(el);
  }
}

async function toggleFavorite(noteId) {
  if (!noteId) return;

  // ログインしていない場合は従来挙動を崩さず案内だけ
  if (!currentUserId) {
    alert('お気に入り機能はログインが必要です');
    return;
  }

  try {
    const res = await fetch('toggle_favorite.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ noteId })
    });

    const data = await res.json().catch(() => null);
    if (!res.ok || !data || data.status !== 'success') {
      throw new Error(data?.message || 'お気に入り更新に失敗しました');
    }

    // サーバーの結果に合わせてローカル状態を更新
    const nextFav = Array.isArray(data?.fav) ? data.fav.map(String) : null;
    if (nextFav) {
      userFavIds = new Set(nextFav);
    } else {
      // 念のため
      if (userFavIds.has(String(noteId))) userFavIds.delete(String(noteId));
      else userFavIds.add(String(noteId));
    }

    // 現在の表示モードも含めて再描画（favoritesタブで外れる/入るを反映）
    renderWithCurrentFilters();
  } catch (e) {
    alert(e?.message || 'お気に入り更新中にエラーが発生しました');
  }
}

function applyViewFilter(items) {
  if (currentView === 'favorites') {
    // ▼▼ 変更: Users/<user_id>.json の fav を優先して絞り込み ▼▼
    if (userFavIds && userFavIds.size) {
      return items.filter(it => it?.id != null && userFavIds.has(String(it.id)));
    }
    // フォールバック（既存データ互換）
    return items.filter(it => hasFlag(it, ['isFavorite', 'favorite', 'starred']));
  }
  if (currentView === 'mine') {
    // ▼▼ 変更: Users/<user_id>.json の post を優先して絞り込み ▼▼
    if (userPostIds && userPostIds.size) {
      return items.filter(it => it?.id != null && userPostIds.has(String(it.id)));
    }
    // フォールバック（既存データ互換）
    return items.filter(it => hasFlag(it, ['isMine', 'mine', 'isOwner', 'own']));
  }
  return items;
}

function hasFlag(item, keys) {
  if (!item) return false;
  return keys.some(key => Boolean(item[key]));
}

function renderWithCurrentFilters() {
  render(applyViewFilter(lastSearchResult));
}

// 検索を実行する非同期関数
async function search() {
  // (3) 検索時にもまず全データをロードする
  const allNotes = await loadNotes();

  // ▼▼ 追加: ユーザー情報を読み込んでタブ絞り込みに反映 ▼▼
  await loadUserData();

  const q = document.getElementById('q').value.trim();
  const cat = document.getElementById('category').value;
  
  // ▼▼ 変更: body も検索対象に含める＋未定義安全化 ▼▼
  const filtered = allNotes.filter(x =>
    (q === '' ||
      (x.title && x.title.includes(q)) ||
      (x.body && x.body.includes(q)) ||
      (x.desc && x.desc.includes(q))
    ) &&
    (cat === '' || x.category === cat)
  );
  
  lastSearchResult = filtered;
  renderWithCurrentFilters();
}

// 検索ボタンとEnterキーのイベントリスナー
document.getElementById('searchBtn').addEventListener('click', search);
document.getElementById('q').addEventListener('keydown', e => {
  if (e.key === 'Enter') {
    e.preventDefault();
    search();
  }
});

function setupViewTabs() {
  const tabs = document.querySelectorAll('.tab-btn');
  if (!tabs.length) return;
  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      const filter = tab.dataset.filter ?? 'all';
      currentView = filter;
      tabs.forEach(btn => {
        const isActive = btn === tab;
        btn.classList.toggle('active', isActive);
        btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
      });
      renderWithCurrentFilters();
    });
  });
}

// (4) ページの読み込みが完了したら、まず全データをロードして描画する
window.addEventListener('DOMContentLoaded', async () => {
  setupViewTabs();
  await search();
});

// サンプルデータはそのまま残します

let currentView = 'all';
let lastSearchResult = [];

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
      const a = document.createElement('a');
      a.href = `gallery.php?id=${encodeURIComponent(it.id)}`;
      a.className = 'card-link';
      a.innerHTML = `
        ${it.cover ? `<img class="thumb" src="${it.cover}" alt="">` : ''}
        <h3>${it.title}</h3>
        <p>${it.category}</p>
        <p>${it.body ?? (it.desc ?? '')}</p>
      `;
      el.appendChild(a);
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

function applyViewFilter(items) {
  if (currentView === 'favorites') {
    return items.filter(it => hasFlag(it, ['isFavorite', 'favorite', 'starred']));
  }
  if (currentView === 'mine') {
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

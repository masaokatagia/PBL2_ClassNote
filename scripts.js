// サンプルデータはそのまま残します
const sample = [
  { title: '応用数学２', category: '共通教育', desc: '微分方程式の解法と応用' },
  { title: 'PBL演習', category: '専門応用', desc: 'チーム開発の基礎' },
  { title: '統計数学', category: '専門応用', desc: '仮説検定とt検定まで' },
  { title: 'ソフトウェア工学', category: '共通教育', desc: '要求定義と設計手法' },
];

// データを読み込む非同期関数
async function loadNotes() {
  let serverNotes = [];
  try {
    // (1) サーバーから get_notes.php を fetch で取得（id欠落の自動付与も実施）
    const response = await fetch('get_notes.php');
    if (response.ok) {
      serverNotes = await response.json();
    }
  } catch (e) {
    // ファイルが存在しない場合(初回起動時など)はコンソールにログを出すだけ
    console.warn('notes.json が見つからないか、読み込めません。');
  }
  
  // (2) サーバーのノートとサンプルデータを結合
  // サーバーのノートは 'body'、サンプルは 'desc' を持っているため、両対応
  const allData = [
    ...serverNotes.map(n => ({ ...n, desc: (n.body ?? '').split('\n')[0] })),
    ...sample
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
    // desc が未定義の場合も考慮
    if (it.id) {
      // クリック範囲を明確にするため、カード内をアンカーでラップ
      el.classList.add('clickable');
      el.dataset.id = it.id;
      const a = document.createElement('a');
      a.href = `gallery.php?id=${encodeURIComponent(it.id)}`;
      a.className = 'card-link';
      a.innerHTML = `<h3>${it.title}</h3><p>${it.category}</p><p>${it.desc ?? ''}</p>`;
      el.appendChild(a);
    } else {
      // サンプルなどidが無いものは非クリック
      el.innerHTML = `<h3>${it.title}</h3><p>${it.category}</p><p>${it.desc ?? ''}</p>`;
    }
    root.appendChild(el);
  }
}

// 検索を実行する非同期関数
async function search() {
  // (3) 検索時にもまず全データをロードする
  const allNotes = await loadNotes();

  const q = document.getElementById('q').value.trim();
  const cat = document.getElementById('category').value;
  
  const filtered = allNotes.filter(x =>
    (q === '' || x.title.includes(q) || (x.desc && x.desc.includes(q))) &&
    (cat === '' || x.category === cat)
  );
  
  render(filtered); // フィルタリング結果を描画
}

// 検索ボタンとEnterキーのイベントリスナー
document.getElementById('searchBtn').addEventListener('click', search);
document.getElementById('q').addEventListener('keydown', e => {
  if (e.key === 'Enter') {
    e.preventDefault();
    search();
  }
});

// (4) ページの読み込みが完了したら、まず全データをロードして描画する
window.addEventListener('DOMContentLoaded', async () => {
  const allNotes = await loadNotes();
  render(allNotes);
});

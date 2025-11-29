<?php
declare(strict_types=1);

require_once __DIR__ . '/../Lib/db.php';
require_once __DIR__ . '/../Middleware/Auth.php';

require_login();
verify_csrf();

$pdo   = getDB();
$uid   = (int)($_SESSION['user_id'] ?? 0);
$route = $_GET['route'] ?? 'mindmaps.list';

/** Sidebar projects */
$projStmt = $pdo->prepare("
  SELECT id, title AS name, color
  FROM projects
  WHERE owner_id=? AND is_archived=0
  ORDER BY created_at DESC
  LIMIT 100
");
$projStmt->execute([$uid]);
$projects = $projStmt->fetchAll(PDO::FETCH_ASSOC);

/** Utilities */
function ensure_mm_owner(PDO $pdo, int $id, int $uid): array {
  $q = $pdo->prepare("
    SELECT m.*, p.title AS project_title
    FROM mindmaps m
    JOIN projects p ON p.id=m.project_id
    WHERE m.id=? AND m.created_by=?
    LIMIT 1
  ");
  $q->execute([$id, $uid]);
  $row = $q->fetch(PDO::FETCH_ASSOC);
  if (!$row) { http_response_code(404); exit('Mind map not found'); }
  return $row;
}

function save_mm_thumbnail(?string $dataUrl, string $prefix='mm'): ?string {
  if (!$dataUrl) return null;
  if (!preg_match('~^data:image/(png|jpeg);base64,~', $dataUrl, $m)) return null;
  $ext = $m[1] === 'jpeg' ? 'jpg' : 'png';
  $data = base64_decode(preg_replace('~^data:image/[^;]+;base64,~', '', $dataUrl), true);
  if ($data === false) return null;

  $dirFs  = dirname(__DIR__, 2) . '/public/uploads/mindmaps';
  $dirWeb = '/uploads/mindmaps';
  if (!is_dir($dirFs)) @mkdir($dirFs, 0775, true);

  $name = $prefix . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
  $path = $dirFs . '/' . $name;
  if (@file_put_contents($path, $data) === false) return null;

  return $dirWeb . '/' . $name;
}

/** LIST */
if ($route === 'mindmaps.list') {
  $stmt = $pdo->prepare("
    SELECT m.id, m.title, m.thumbnail_path, m.updated_at, p.title AS project
    FROM mindmaps m
    JOIN projects p ON p.id = m.project_id
    WHERE m.created_by = ?
    ORDER BY m.updated_at DESC, m.id DESC
    LIMIT 200
  ");
  $stmt->execute([$uid]);
  $mindmaps = $stmt->fetchAll(PDO::FETCH_ASSOC);

  ob_start(); ?>
    <h2>Mind maps</h2>
    <p><a class="btn" href="index.php?route=mindmaps.new">+ New Mind map</a></p>
    <ul class="list">
      <?php foreach ($mindmaps as $m): ?>
        <li>
          <a href="index.php?route=mindmaps.view&id=<?= (int)$m['id'] ?>">
            <?= htmlspecialchars($m['title']) ?>
          </a>
          <div class="muted">
            <?= htmlspecialchars($m['project']) ?> • updated <?= htmlspecialchars($m['updated_at']) ?>
          </div>
          <?php if (!empty($m['thumbnail_path'])): ?>
            <div style="margin-top:8px">
              <img src="<?= htmlspecialchars($m['thumbnail_path']) ?>" alt="thumb"
                   style="max-width:280px;border-radius:10px;border:1px solid #232842">
            </div>
          <?php endif; ?>
        </li>
      <?php endforeach; if (!$mindmaps): ?>
        <li class="muted">No mind maps yet.</li>
      <?php endif; ?>
    </ul>
  <?php
  $content = ob_get_clean();
  $title = 'Mind maps';
  require __DIR__ . '/../Views/layout.php';
  exit;
}

/** NEW */
if ($route === 'mindmaps.new') {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = (int)($_POST['project_id'] ?? 0);
    $title      = trim((string)($_POST['title'] ?? ''));
    $data_json  = trim((string)($_POST['data_json'] ?? ''));
    $thumb_b64  = $_POST['thumb_data_url'] ?? null;

    json_decode($data_json);
    $json_ok = (json_last_error() === JSON_ERROR_NONE);

    if ($project_id && $title !== '' && $json_ok) {
      $ins = $pdo->prepare("INSERT INTO mindmaps (project_id, title, data_json, created_by) VALUES (?,?,?,?)");
      $ins->execute([$project_id, $title, $data_json, $uid]);
      $newId = (int)$pdo->lastInsertId();

      if ($thumb = save_mm_thumbnail($thumb_b64, 'mm'.$newId)) {
        $up = $pdo->prepare("UPDATE mindmaps SET thumbnail_path=? WHERE id=? AND created_by=?");
        $up->execute([$thumb, $newId, $uid]);
      }
      header('Location: index.php?route=mindmaps.view&id=' . $newId); exit;
    }
    $err = !$json_ok ? 'Invalid JSON for mind map.' : 'Project and title are required.';
  }

  $mm = [
    'id'=>null,
    'project_id'=>($projects[0]['id'] ?? 0),
    'title'=>'',
    'data_json'=>'{"nodes":[{"id":"root","x":200,"y":200,"text":"Topic"}],"edges":[]}',
    'project_title'=>''
  ];
  goto render_mm_editor;
}

/** VIEW */
if ($route === 'mindmaps.view') {
  $id = (int)($_GET['id'] ?? 0);
  $mm = ensure_mm_owner($pdo, $id, $uid);
  $payload = $mm['data_json'] ?: '{"nodes":[],"edges":[]}';

  ob_start(); ?>
    <h2><?= htmlspecialchars($mm['title']) ?></h2>
    <p class="muted">Project: <?= htmlspecialchars($mm['project_title']) ?> • Updated: <?= htmlspecialchars($mm['updated_at']) ?></p>

    <style>
      #mapv { width:100%; height:520px; background:#0b0e1a; border:1px solid #232842; border-radius:12px; position:relative; overflow:hidden; }
      .nv { position:absolute; padding:8px 10px; border-radius:12px; background:#171a2b; border:1px solid #232842; color:#e5e7eb; }
      svg { position:absolute; inset:0; pointer-events:none; }
      line { stroke:#6b7280; stroke-width:2; }
    </style>

    <div id="mapv"><svg id="edgesv"></svg></div>

    <script>
      const data = (function(){ try { return JSON.parse(<?= json_encode($payload) ?>); } catch(e){ return {nodes:[],edges:[]} } })();
      const box = document.getElementById('mapv');
      const svg = document.getElementById('edgesv');

      function draw(){
        box.querySelectorAll('.nv').forEach(n=>n.remove());
        svg.innerHTML='';
        const rect = box.getBoundingClientRect();
        svg.setAttribute('width', rect.width);
        svg.setAttribute('height', 520);

        for(const n of data.nodes){
          const el = document.createElement('div'); el.className='nv';
          el.style.left=n.x+'px'; el.style.top=n.y+'px'; el.textContent = n.text||'';
          box.appendChild(el);
        }
        function by(id){ return data.nodes.find(n=>n.id===id); }
        for(const e of data.edges){
          const a=by(e.from), b=by(e.to); if(!a||!b) continue;
          const x1=a.x+60,y1=a.y+18,x2=b.x+60,y2=b.y+18;
          const line=document.createElementNS('http://www.w3.org/2000/svg','line');
          line.setAttribute('x1',x1); line.setAttribute('y1',y1);
          line.setAttribute('x2',x2); line.setAttribute('y2',y2);
          svg.appendChild(line);
        }
      }
      window.addEventListener('resize', draw); draw();
    </script>

    <p style="margin-top:12px">
      <a class="btn" href="index.php?route=mindmaps.edit&id=<?= (int)$mm['id'] ?>">Edit</a>
      <form method="post" action="index.php?route=mindmaps.delete&id=<?= (int)$mm['id'] ?>" style="display:inline" onsubmit="return confirm('Delete this mind map?');">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <button class="btn" type="submit">Delete</button>
      </form>
      <a class="btn" href="index.php?route=mindmaps.list">Back</a>
    </p>
  <?php
  $content = ob_get_clean();
  $title = 'Mind map';
  require __DIR__ . '/../Views/layout.php';
  exit;
}

/** EDIT */
if ($route === 'mindmaps.edit') {
  $id = (int)($_GET['id'] ?? 0);
  $mm = ensure_mm_owner($pdo, $id, $uid);

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = (int)($_POST['project_id'] ?? 0);
    $title      = trim((string)($_POST['title'] ?? ''));
    $data_json  = trim((string)($_POST['data_json'] ?? ''));
    $thumb_b64  = $_POST['thumb_data_url'] ?? null;

    json_decode($data_json);
    $json_ok = (json_last_error() === JSON_ERROR_NONE);

    if ($project_id && $title !== '' && $json_ok) {
      $upd = $pdo->prepare("UPDATE mindmaps SET project_id=?, title=?, data_json=? WHERE id=? AND created_by=?");
      $upd->execute([$project_id, $title, $data_json, $id, $uid]);

      if ($thumb = save_mm_thumbnail($thumb_b64, 'mm'.$id)) {
        $up = $pdo->prepare("UPDATE mindmaps SET thumbnail_path=? WHERE id=? AND created_by=?");
        $up->execute([$thumb, $id, $uid]);
      }
      header('Location: index.php?route=mindmaps.view&id=' . $id); exit;
    }
    $err = !$json_ok ? 'Invalid JSON for mind map.' : 'Project and title are required.';
  }

  render_mm_editor:
  ob_start(); ?>
    <h2><?= $mm['id'] ? 'Edit' : 'New' ?> Mind map</h2>
    <?php if (!empty($err)): ?><p style="color:#f87171"><?= htmlspecialchars($err) ?></p><?php endif; ?>

    <style>
      #map { width:100%; height:520px; background:#0b0e1a; border:1px solid #232842; border-radius:12px; position:relative; overflow:hidden; }
      .node { position:absolute; padding:8px 10px; border-radius:12px; background:#171a2b; border:1px solid #232842; color:#e5e7eb; cursor:grab; user-select:none; }
      .node.sel { outline:2px solid #7c5cff; }
      .hud { display:flex; gap:8px; margin:10px 0 }
      .hud > * { padding:6px 10px; border-radius:8px; border:1px solid #232842; background:#0e1120; color:#e5e7eb }
      svg { position:absolute; inset:0; pointer-events:none; }
      line { stroke:#6b7280; stroke-width:2; }
    </style>

    <form id="mm-form" method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="thumb_data_url" id="thumb_data_url">
      <label>Project</label>
      <select name="project_id" required style="width:100%;padding:10px;margin:6px 0">
        <?php foreach ($projects as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= (int)$p['id']===(int)($mm['project_id']??0)?'selected':'' ?>>
            <?= htmlspecialchars($p['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <label>Title</label>
      <input name="title" required value="<?= htmlspecialchars($mm['title'] ?? '') ?>" style="width:100%;padding:10px;margin:6px 0" />

      <div class="hud">
        <button type="button" id="add">+ Node</button>
        <button type="button" id="link">Link: select two</button>
        <button type="button" id="del">Delete selected</button>
        <button type="button" id="center">Center</button>
      </div>

      <div id="map"><svg id="edges"></svg></div>
      <input type="hidden" name="data_json" id="mm_json" />
      <p style="margin-top:10px"><button class="btn" type="submit">Save</button></p>
    </form>

    <script>
      const container = document.getElementById('map');
      const svg = document.getElementById('edges');
      const form = document.getElementById('mm-form');
      const mmEl = document.getElementById('mm_json');
      const thumbEl = document.getElementById('thumb_data_url');

      const data = (function(){
        try { return JSON.parse(<?= json_encode($mm['data_json'] ?? '{"nodes":[{"id":"root","x":200,"y":200,"text":"Topic"}],"edges":[]}') ?>); }
        catch(e){ return {nodes:[{id:'root',x:200,y:200,text:'Topic'}],edges:[]}; }
      })();

      function uid(){ return 'n' + Math.random().toString(36).slice(2,8); }

      // Selection helpers
      let selected = [];
      function clearSelection(){
        selected.forEach(e=>e.classList.remove('sel'));
        selected = [];
      }
      function toggleSelect(el){
        if (!el) { clearSelection(); return; }
        if (selected.includes(el)) {
          el.classList.remove('sel');
          selected = selected.filter(x=>x!==el);
        } else {
          el.classList.add('sel');
          selected.push(el);
        }
      }

      function nodeById(id){ return data.nodes.find(n=>n.id===id); }

      function drawEdges(){
        svg.innerHTML='';
        const rect = container.getBoundingClientRect();
        svg.setAttribute('width', rect.width);
        svg.setAttribute('height', 520);
        for(const e of data.edges){
          const a = nodeById(e.from), b = nodeById(e.to);
          if(!a || !b) continue;
          const x1 = a.x + 60, y1 = a.y + 18, x2 = b.x + 60, y2 = b.y + 18;
          const line = document.createElementNS('http://www.w3.org/2000/svg','line');
          line.setAttribute('x1', x1); line.setAttribute('y1', y1);
          line.setAttribute('x2', x2); line.setAttribute('y2', y2);
          svg.appendChild(line);
        }
      }

      function makeNode(n){
        const el = document.createElement('div');
        el.className='node';
        el.style.left = n.x+'px'; el.style.top = n.y+'px';
        el.dataset.id = n.id;
        el.contentEditable = 'true';
        el.textContent = n.text || 'New';
        el.addEventListener('input', ()=>{ n.text = el.textContent.slice(0,60); });

        let drag=false, offX=0, offY=0, startX=0, startY=0, moved=false;

        // Prevent container click from clearing selection after we select this node
        el.addEventListener('click', (e)=> e.stopPropagation());

        el.addEventListener('mousedown', (e)=>{
          drag=true; moved=false;
          offX=e.offsetX; offY=e.offsetY;
          startX=e.clientX; startY=e.clientY;
          el.style.cursor='grabbing';
          e.stopPropagation();
        });

        window.addEventListener('mousemove', (e)=>{
          if(!drag) return;
          const r = container.getBoundingClientRect();
          const nx = e.clientX - r.left - offX;
          const ny = e.clientY - r.top  - offY;
          if (Math.abs(nx - n.x) > 0.5 || Math.abs(ny - n.y) > 0.5) moved = true; // jitter filter
          n.x = nx; n.y = ny;
          el.style.left=n.x+'px'; el.style.top=n.y+'px';
          drawEdges();
        });

        window.addEventListener('mouseup', (e)=>{
          if(!drag) return;
          el.style.cursor='grab';
          drag=false;
          const dist = Math.hypot((e.clientX||startX)-startX, (e.clientY||startY)-startY);
          if (!moved || dist < 4) toggleSelect(el); // treat as click
        });

        return el;
      }

      function renderNodes(){
        container.querySelectorAll('.node').forEach(n=>n.remove());
        for(const n of data.nodes){ container.appendChild(makeNode(n)); }
        drawEdges();
      }

      // HUD actions
      document.getElementById('add').onclick = ()=>{
        const n = {id:uid(), x: 80 + Math.random()*300, y: 80 + Math.random()*300, text:'Idea'};
        data.nodes.push(n); renderNodes();
      };

      document.getElementById('link').onclick = ()=>{
        if (selected.length!==2) { alert('Select two nodes to link.'); return; }
        const a = selected[0].dataset.id, b = selected[1].dataset.id;
        if (a===b) return;
        if (!data.edges.find(e=> (e.from===a&&e.to===b) || (e.from===b&&e.to===a))) {
          data.edges.push({from:a,to:b}); drawEdges();
        }
        clearSelection();
      };

      document.getElementById('del').onclick = ()=>{
        if (!selected.length) return;
        const ids = new Set(selected.map(el=>el.dataset.id));
        data.nodes = data.nodes.filter(n=>!ids.has(n.id));
        data.edges = data.edges.filter(e=>!ids.has(e.from) && !ids.has(e.to));
        clearSelection(); renderNodes();
      };

      // Proper "Center": center bounding box of nodes in the visible area
      function centerNodesInView(){
        if (!data.nodes.length) return;
        const rect = container.getBoundingClientRect();
        const W = rect.width, H = 520; // keep in sync with svg height

        let minX=Infinity, minY=Infinity, maxX=-Infinity, maxY=-Infinity;
        for (const n of data.nodes) {
          const w=120, h=36; // node box size
          minX = Math.min(minX, n.x);
          minY = Math.min(minY, n.y);
          maxX = Math.max(maxX, n.x + w);
          maxY = Math.max(maxY, n.y + h);
        }
        const bboxW = Math.max(1, maxX - minX);
        const bboxH = Math.max(1, maxY - minY);

        const dx = (W - bboxW)/2 - minX;
        const dy = (H - bboxH)/2 - minY;

        for (const n of data.nodes) { n.x += dx; n.y += dy; }
        renderNodes();
      }
      document.getElementById('center').onclick = centerNodesInView;

      // Only clear when clicking the blank canvas (not a node)
      container.addEventListener('click', (e)=>{
        if (e.target === container) clearSelection();
      });

      window.addEventListener('resize', drawEdges);
      renderNodes();

      // thumbnail on submit
      function makeThumb(){
        const W = Math.max(600, container.clientWidth), H = 400;
        const c = document.createElement('canvas'); c.width=W; c.height=H;
        const g = c.getContext('2d'); g.fillStyle='#0b0e1a'; g.fillRect(0,0,W,H);
        g.strokeStyle='#6b7280'; g.lineWidth=2;

        function drawNode(n){
          g.fillStyle='#171a2b'; g.strokeStyle='#232842'; g.lineWidth=1.5;
          const w=120,h=36; g.fillRect(n.x, n.y, w, h); g.strokeRect(n.x, n.y, w, h);
          g.fillStyle='#e5e7eb'; g.font='14px system-ui';
          g.fillText((n.text||'').slice(0,18), n.x+8, n.y+22);
        }
        // edges
        g.strokeStyle='#6b7280'; g.lineWidth=2;
        for(const e of data.edges){
          const a = nodeById(e.from), b = nodeById(e.to); if(!a||!b) continue;
          g.beginPath(); g.moveTo(a.x+60, a.y+18); g.lineTo(b.x+60, b.y+18); g.stroke();
        }
        // nodes
        for(const n of data.nodes){ drawNode(n); }
        return c.toDataURL('image/png');
      }

      form.addEventListener('submit', ()=>{
        data.nodes.forEach(n=>{ if (typeof n.text==='string') n.text = n.text.slice(0,120); });
        mmEl.value = JSON.stringify(data);
        try { thumbEl.value = makeThumb(); } catch(e) { thumbEl.value=''; }
      });
    </script>
  <?php
  $content = ob_get_clean();
  $title = ($mm['id'] ? 'Edit' : 'New') . ' Mind map';
  require __DIR__ . '/../Views/layout.php';
  exit;
}

/** DELETE */
if ($route === 'mindmaps.delete') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method Not Allowed'); }
  $id = (int)($_GET['id'] ?? 0);
  $chk = $pdo->prepare("SELECT id FROM mindmaps WHERE id=? AND created_by=? LIMIT 1");
  $chk->execute([$id, $uid]);
  if ($chk->fetch(PDO::FETCH_ASSOC)) {
    $del = $pdo->prepare("DELETE FROM mindmaps WHERE id=? AND created_by=?");
    $del->execute([$id, $uid]);
  }
  header('Location: index.php?route=mindmaps.list'); exit;
}

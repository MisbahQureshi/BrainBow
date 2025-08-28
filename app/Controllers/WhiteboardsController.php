<?php
declare(strict_types=1);

require_once __DIR__ . '/../Lib/db.php';
require_once __DIR__ . '/../Middleware/Auth.php';

require_login();
verify_csrf();

$pdo   = getDB();
$uid   = (int)$_SESSION['user_id'];
$route = $_GET['route'] ?? 'whiteboards.list';

/** Sidebar projects for layout */
$projStmt = $pdo->prepare("SELECT id, title AS name, color FROM projects WHERE owner_id=? AND is_archived=0 ORDER BY created_at DESC LIMIT 100");
$projStmt->execute([$uid]);
$projects = $projStmt->fetchAll();

/** Utilities */
function ensure_wb_owner(PDO $pdo, int $id, int $uid): array {
  $q = $pdo->prepare("SELECT w.*, p.title AS project_title FROM whiteboards w JOIN projects p ON p.id=w.project_id WHERE w.id=? AND w.created_by=?");
  $q->execute([$id, $uid]);
  $row = $q->fetch();
  if (!$row) { http_response_code(404); exit('Whiteboard not found'); }
  return $row;
}

/** Save a base64 data URL PNG/JPEG to /public/uploads/whiteboards and return relative web path */
function save_wb_thumbnail(?string $dataUrl, string $prefix='wb'): ?string {
  if (!$dataUrl) return null;
  if (!preg_match('~^data:image/(png|jpeg);base64,~', $dataUrl, $m)) return null;
  $ext = $m[1] === 'jpeg' ? 'jpg' : 'png';
  $data = base64_decode(preg_replace('~^data:image/[^;]+;base64,~', '', $dataUrl), true);
  if ($data === false) return null;

  $dirFs  = dirname(__DIR__, 2) . '/public/uploads/whiteboards';
  $dirWeb = '/uploads/whiteboards';
  if (!is_dir($dirFs)) @mkdir($dirFs, 0775, true);

  $name = $prefix . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
  $path = $dirFs . '/' . $name;
  if (@file_put_contents($path, $data) === false) return null;

  return $dirWeb . '/' . $name;
}

/** LIST */
if ($route === 'whiteboards.list') {
  $stmt = $pdo->prepare("
    SELECT w.id, w.title, w.thumbnail_path, w.updated_at, p.title AS project
    FROM whiteboards w
    JOIN projects p ON p.id = w.project_id
    WHERE w.created_by = ?
    ORDER BY w.updated_at DESC, w.id DESC
    LIMIT 200
  ");
  $stmt->execute([$uid]);
  $whiteboards = $stmt->fetchAll();

  ob_start(); ?>
    <h2>Whiteboards</h2>
    <p><a class="btn" href="index.php?route=whiteboards.new">+ New Whiteboard</a></p>
    <ul class="list">
      <?php foreach ($whiteboards as $w): ?>
        <li>
          <a href="index.php?route=whiteboards.view&id=<?= (int)$w['id'] ?>">
            <?= htmlspecialchars($w['title']) ?>
          </a>
          <div class="muted">
            <?= htmlspecialchars($w['project']) ?> • updated <?= htmlspecialchars($w['updated_at']) ?>
          </div>
          <?php if (!empty($w['thumbnail_path'])): ?>
            <div style="margin-top:8px"><img src="<?= htmlspecialchars($w['thumbnail_path']) ?>" alt="thumb" style="max-width:280px;border-radius:10px;border:1px solid #232842"></div>
          <?php endif; ?>
        </li>
      <?php endforeach; if (!$whiteboards): ?><li class="muted">No whiteboards yet.</li><?php endif; ?>
    </ul>
  <?php
  $content = ob_get_clean();
  $title = 'Whiteboards';
  require __DIR__ . '/../Views/layout.php';
  exit;
}

/** NEW (editor) */
if ($route === 'whiteboards.new') {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = (int)($_POST['project_id'] ?? 0);
    $title      = trim($_POST['title'] ?? '');
    $data_json  = trim($_POST['data_json'] ?? '');
    $thumb_b64  = $_POST['thumb_data_url'] ?? null;

    json_decode($data_json);
    $json_ok = (json_last_error() === JSON_ERROR_NONE);

    if ($project_id && $title !== '' && $json_ok) {
      $ins = $pdo->prepare("INSERT INTO whiteboards (project_id, title, data_json, created_by) VALUES (?,?,?,?)");
      $ins->execute([$project_id, $title, $data_json, $uid]);
      $newId = (int)$pdo->lastInsertId();

      if ($thumb = save_wb_thumbnail($thumb_b64, 'wb'.$newId)) {
        $up = $pdo->prepare("UPDATE whiteboards SET thumbnail_path=? WHERE id=? AND created_by=?");
        $up->execute([$thumb, $newId, $uid]);
      }
      header('Location: index.php?route=whiteboards.view&id=' . $newId); exit;
    }
    $err = !$json_ok ? 'Invalid JSON payload from canvas.' : 'Project and title are required.';
  }

  // Fake $wb shape for editor reuse
  $wb = ['id'=>null,'project_id'=>0,'title'=>'','data_json'=>'{"strokes":[]}','project_title'=>''];
  goto render_wb_editor;
}

/** VIEW (renders saved drawing) */
if ($route === 'whiteboards.view') {
  $id = (int)($_GET['id'] ?? 0);
  $wb = ensure_wb_owner($pdo, $id, $uid);
  $payload = $wb['data_json'] ?: '{"strokes":[]}';

  ob_start(); ?>
    <h2><?= htmlspecialchars($wb['title']) ?></h2>
    <p class="muted">Project: <?= htmlspecialchars($wb['project_title']) ?> • Updated: <?= htmlspecialchars($wb['updated_at']) ?></p>

    <canvas id="wb-view" style="width:100%;height:520px;background:#0b0e1a;border:1px solid #232842;border-radius:12px"></canvas>

    <script>
      const strokes = (function(){ try { return JSON.parse(<?= json_encode($payload) ?>).strokes||[] } catch(e){ return [] } })();
      const c = document.getElementById('wb-view'), g=c.getContext('2d');
      function fit(){ const r=c.getBoundingClientRect(); c.width=r.width; c.height=520; draw(); }
      function draw(){
        g.clearRect(0,0,c.width,c.height);
        for(const s of strokes){
          g.lineCap='round'; g.lineJoin='round'; g.lineWidth=s.size||4;
          if (s.tool==='eraser'){ g.globalCompositeOperation='destination-out'; g.strokeStyle='rgba(0,0,0,1)'; }
          else { g.globalCompositeOperation='source-over'; g.strokeStyle=s.color||'#fff'; }
          g.beginPath();
          for(let i=0;i<s.points.length;i++){ const p=s.points[i]; if(i===0) g.moveTo(p.x,p.y); else g.lineTo(p.x,p.y); }
          g.stroke();
        }
        g.globalCompositeOperation='source-over';
      }
      window.addEventListener('resize', fit); fit();
    </script>

    <p style="margin-top:12px">
      <a class="btn" href="index.php?route=whiteboards.edit&id=<?= (int)$wb['id'] ?>">Edit</a>
      <form method="post" action="index.php?route=whiteboards.delete&id=<?= (int)$wb['id'] ?>" style="display:inline" onsubmit="return confirm('Delete this whiteboard?');">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <button class="btn" type="submit">Delete</button>
      </form>
      <a class="btn" href="index.php?route=whiteboards.list">Back</a>
    </p>
  <?php
  $content = ob_get_clean();
  $title = 'Whiteboard';
  require __DIR__ . '/../Views/layout.php';
  exit;
}

/** EDIT (editor) */
if ($route === 'whiteboards.edit') {
  $id = (int)($_GET['id'] ?? 0);
  $wb = ensure_wb_owner($pdo, $id, $uid);

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = (int)($_POST['project_id'] ?? 0);
    $title      = trim($_POST['title'] ?? '');
    $data_json  = trim($_POST['data_json'] ?? '');
    $thumb_b64  = $_POST['thumb_data_url'] ?? null;

    json_decode($data_json);
    $json_ok = (json_last_error() === JSON_ERROR_NONE);

    if ($project_id && $title !== '' && $json_ok) {
      $upd = $pdo->prepare("UPDATE whiteboards SET project_id=?, title=?, data_json=? WHERE id=? AND created_by=?");
      $upd->execute([$project_id, $title, $data_json, $id, $uid]);

      if ($thumb = save_wb_thumbnail($thumb_b64, 'wb'.$id)) {
        $up = $pdo->prepare("UPDATE whiteboards SET thumbnail_path=? WHERE id=? AND created_by=?");
        $up->execute([$thumb, $id, $uid]);
      }
      header('Location: index.php?route=whiteboards.view&id=' . $id); exit;
    }
    $err = !$json_ok ? 'Invalid JSON payload from canvas.' : 'Project and title are required.';
  }

  render_wb_editor:
  ob_start(); ?>
    <h2><?= $wb['id'] ? 'Edit' : 'New' ?> Whiteboard</h2>
    <?php if (!empty($err)): ?><p style="color:#f87171"><?= htmlspecialchars($err) ?></p><?php endif; ?>

    <style>
      .wb-toolbar { display:flex; gap:8px; margin:10px 0 }
      .wb-toolbar > * { padding:6px 10px; border-radius:8px; border:1px solid #232842; background:#0e1120; color:#e5e7eb }
      #wb-canvas { width:100%; height:520px; background:#0b0e1a; border:1px solid #232842; border-radius:12px; touch-action:none }
    </style>

    <form id="wb-form" method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="thumb_data_url" id="thumb_data_url">
      <label>Project</label>
      <select name="project_id" required style="width:100%;padding:10px;margin:6px 0">
        <option value="">-- Select project --</option>
        <?php foreach ($projects as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= (int)$p['id']===(int)($wb['project_id']??0)?'selected':'' ?>>
            <?= htmlspecialchars($p['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <label>Title</label>
      <input name="title" required value="<?= htmlspecialchars($wb['title'] ?? '') ?>" style="width:100%;padding:10px;margin:6px 0" />

      <div class="wb-toolbar">
        <select id="tool">
          <option value="pen">✏️ Pen</option>
          <option value="eraser">🩹 Eraser</option>
        </select>
        <label>Size <input id="size" type="range" min="1" max="30" value="4" /></label>
        <input id="color" type="color" value="#ffffff" />
        <button type="button" id="undo">Undo</button>
        <button type="button" id="clear">Clear</button>
      </div>

      <canvas id="wb-canvas"></canvas>
      <input type="hidden" name="data_json" id="data_json" />
      <p style="margin-top:10px"><button class="btn" type="submit">Save</button></p>
    </form>

    <script>
      const canvas = document.getElementById('wb-canvas');
      const ctx = canvas.getContext('2d');
      const toolEl = document.getElementById('tool');
      const sizeEl = document.getElementById('size');
      const colorEl = document.getElementById('color');
      const undoEl = document.getElementById('undo');
      const clearEl = document.getElementById('clear');
      const dataEl = document.getElementById('data_json');
      const formEl = document.getElementById('wb-form');
      const thumbEl = document.getElementById('thumb_data_url');

      function fitCanvas(){
        const r = canvas.getBoundingClientRect();
        // preserve image on resize
        const prev = ctx.getImageData(0,0,canvas.width||1,canvas.height||1);
        canvas.width = r.width; canvas.height = 520;
        ctx.putImageData(prev,0,0); redraw();
      }
      window.addEventListener('resize', fitCanvas);

      // model
      let strokes = [];
      try { strokes = JSON.parse(<?= json_encode($wb['data_json'] ?? '{"strokes":[]}') ?>).strokes || []; } catch(e){ strokes=[]; }
      let drawing = false, current = null;

      function redraw(){
        ctx.clearRect(0,0,canvas.width,canvas.height);
        for(const s of strokes){
          ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.lineWidth = s.size||4;
          if (s.tool==='eraser'){ ctx.globalCompositeOperation='destination-out'; ctx.strokeStyle='rgba(0,0,0,1)'; }
          else { ctx.globalCompositeOperation='source-over'; ctx.strokeStyle=s.color||'#fff'; }
          ctx.beginPath();
          for(let i=0;i<s.points.length;i++){ const p=s.points[i]; if(i===0) ctx.moveTo(p.x,p.y); else ctx.lineTo(p.x,p.y); }
          ctx.stroke();
        }
        ctx.globalCompositeOperation='source-over';
      }

      function pt(e){
        const rect = canvas.getBoundingClientRect();
        const x = (e.touches? e.touches[0].clientX : e.clientX) - rect.left;
        const y = (e.touches? e.touches[0].clientY : e.clientY) - rect.top;
        return {x, y};
      }

      function start(e){
        e.preventDefault(); drawing = true;
        current = { tool: toolEl.value, size: parseInt(sizeEl.value,10), color: colorEl.value, points:[ pt(e) ] };
        strokes.push(current); redraw();
      }
      function move(e){ if(!drawing) return; current.points.push(pt(e)); redraw(); }
      function end(){ if(!drawing) return; drawing=false; current=null; }

      canvas.addEventListener('mousedown', start);
      canvas.addEventListener('mousemove', move);
      window.addEventListener('mouseup', end);
      canvas.addEventListener('touchstart', start, {passive:false});
      canvas.addEventListener('touchmove', move, {passive:false});
      canvas.addEventListener('touchend', end);

      undoEl.onclick = ()=>{ strokes.pop(); redraw(); };
      clearEl.onclick = ()=>{ if(confirm('Clear board?')) { strokes=[]; redraw(); } };

      formEl.addEventListener('submit', ()=>{
        dataEl.value = JSON.stringify({strokes});
        // capture thumbnail (~600px wide)
        try { thumbEl.value = canvas.toDataURL('image/png'); } catch(e) { thumbEl.value=''; }
      });

      fitCanvas(); redraw();
    </script>
  <?php
  $content = ob_get_clean();
  $title = ($wb['id'] ? 'Edit' : 'New') . ' Whiteboard';
  require __DIR__ . '/../Views/layout.php';
  exit;
}

/** DELETE */
if ($route === 'whiteboards.delete') {
  $id = (int)($_GET['id'] ?? 0);
  $chk = $pdo->prepare("SELECT id FROM whiteboards WHERE id=? AND created_by=?");
  $chk->execute([$id, $uid]);
  if ($chk->fetch()) {
    $del = $pdo->prepare("DELETE FROM whiteboards WHERE id=? AND created_by=?");
    $del->execute([$id, $uid]);
  }
  header('Location: index.php?route=whiteboards.list'); exit;
}

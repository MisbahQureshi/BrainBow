<?php
declare(strict_types=1);

require_once __DIR__ . '/../Lib/db.php';
require_once __DIR__ . '/../Middleware/Auth.php';

require_login();
$pdo = getDB();
$uid = (int)($_SESSION['user_id'] ?? 0);

$route = $_GET['route'] ?? 'projects.new';

/** check if project exists and is owned by current user */
function ensure_project_owner(PDO $pdo, int $projectId, int $uid): array {
  $stmt = $pdo->prepare("SELECT id, title, color, description, course_code, created_at, updated_at
                         FROM projects
                         WHERE id=? AND owner_id=? AND is_archived=0
                         LIMIT 1");
  $stmt->execute([$projectId, $uid]);
  $proj = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$proj) { http_response_code(404); exit('Project not found'); }
  return $proj;
}

/** Fetch sidebar projects*/
function fetch_sidebar_projects(PDO $pdo, int $uid): array {
  $stmt = $pdo->prepare("SELECT id, title AS name, color
                         FROM projects
                         WHERE owner_id=? AND is_archived=0
                         ORDER BY created_at DESC
                         LIMIT 20");
  $stmt->execute([$uid]);
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** CREATE PROJECT */
if ($route === 'projects.new') {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $title = trim((string)($_POST['title'] ?? ''));
    $color = trim((string)($_POST['color'] ?? '#6c5ce7'));

    if ($title !== '') {
      $stmt = $pdo->prepare("INSERT INTO projects(owner_id,title,color) VALUES (?,?,?)");
      $stmt->execute([$uid, $title, $color]);
      header('Location: index.php?route=dashboard');
      exit;
    }
    $err = 'Title required';
  }

  $projects = fetch_sidebar_projects($pdo, $uid);

  ob_start(); ?>
  <h2>Create Project</h2>
  <?php if (!empty($err)): ?>
    <p style="color:#f87171"><?= htmlspecialchars($err) ?></p>
  <?php endif; ?>
  <form method="post" style="max-width:420px">
    <!-- If using verify_csrf(), include: <input type="hidden" name="csrf" value="<?= csrf_token() ?>"> -->
    <label>Title</label>
    <input name="title" required style="width:100%;padding:10px;margin:6px 0" />
    <label>Color</label>
    <input name="color" value="#6c5ce7" type="color" style="width:100%;padding:10px;margin:6px 0;height:40px" />
    <button class="btn" type="submit">Create</button>
  </form>
  <?php
  $content = ob_get_clean();
  $title = 'New Project';
  require __DIR__ . '/../Views/layout.php';
  exit;
}

/** DELETE PROJECT */
if ($route === 'projects.delete') {
  $id = (int)($_GET['id'] ?? 0);
  $p = ensure_project_owner($pdo, $id, $uid); // verify

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("DELETE FROM projects WHERE id=? AND owner_id=?");
    $stmt->execute([$id, $uid]);
    header('Location: index.php?route=dashboard');
    exit;
  }

  $projects = fetch_sidebar_projects($pdo, $uid);
  ob_start(); ?>
  <h2>Delete Project</h2>
  <p>Are you sure you want to permanently delete
     <strong><?= htmlspecialchars($p['title']) ?></strong> and all its related data?</p>
  <form method="post" style="margin-top:20px">
    <button type="submit" class="btn" style="background:#ef4444">Yes, Delete</button>
    <a href="index.php?route=projects.view&id=<?= (int)$id ?>" class="btn">Cancel</a>
  </form>
  <?php
  $content = ob_get_clean();
  $title = 'Delete Project';
  require __DIR__ . '/../Views/layout.php';
  exit;
}

/** PROJECT OVERVIEW */
if ($route === 'projects.view') {
  $id = (int)($_GET['id'] ?? 0);
  
  $projects = fetch_sidebar_projects($pdo, $uid);

  $p = ensure_project_owner($pdo, $id, $uid);

  // TODOS
  $todos = $pdo->prepare("
    SELECT id, title, status, priority, due_date, updated_at
    FROM todos
    WHERE project_id=? AND created_by=?
    ORDER BY FIELD(status,'todo','doing','done'), priority DESC, COALESCE(due_date,'9999-12-31'), updated_at DESC
    LIMIT 8
  ");
  $todos->execute([$id, $uid]);
  $todos = $todos->fetchAll(PDO::FETCH_ASSOC);

  // NOTES
  $notes = $pdo->prepare("
    SELECT id, title, is_pinned, updated_at
    FROM notes
    WHERE project_id=? AND created_by=?
    ORDER BY is_pinned DESC, updated_at DESC
    LIMIT 6
  ");
  $notes->execute([$id, $uid]);
  $notes = $notes->fetchAll(PDO::FETCH_ASSOC);

  // EVENTS upcoming first
  $events = $pdo->prepare("
    SELECT id, title, start_datetime, end_datetime, location, all_day
    FROM events
    WHERE project_id=? AND created_by=?
    ORDER BY start_datetime ASC
    LIMIT 6
  ");
  $events->execute([$id, $uid]);
  $events = $events->fetchAll(PDO::FETCH_ASSOC);

  // MIND MAPS
  $mindmaps = $pdo->prepare("
    SELECT id, title, thumbnail_path, updated_at
    FROM mindmaps
    WHERE project_id=? AND created_by=?
    ORDER BY updated_at DESC
    LIMIT 6
  ");
  $mindmaps->execute([$id, $uid]);
  $mindmaps = $mindmaps->fetchAll(PDO::FETCH_ASSOC);

  // WHITEBOARDS
  $whiteboards = $pdo->prepare("
    SELECT id, title, thumbnail_path, updated_at
    FROM whiteboards
    WHERE project_id=? AND created_by=?
    ORDER BY updated_at DESC
    LIMIT 6
  ");
  $whiteboards->execute([$id, $uid]);
  $whiteboards = $whiteboards->fetchAll(PDO::FETCH_ASSOC);

  // Quick counts
  $countTodos = $pdo->prepare("SELECT COUNT(*) FROM todos WHERE project_id=? AND created_by=?");
  $countTodos->execute([$id, $uid]); $todoCount = (int)$countTodos->fetchColumn();

  $countNotes = $pdo->prepare("SELECT COUNT(*) FROM notes WHERE project_id=? AND created_by=?");
  $countNotes->execute([$id, $uid]); $noteCount = (int)$countNotes->fetchColumn();

  $countEvents = $pdo->prepare("SELECT COUNT(*) FROM events WHERE project_id=? AND created_by=?");
  $countEvents->execute([$id, $uid]); $eventCount = (int)$countEvents->fetchColumn();

  $countMM = $pdo->prepare("SELECT COUNT(*) FROM mindmaps WHERE project_id=? AND created_by=?");
  $countMM->execute([$id, $uid]); $mmCount = (int)$countMM->fetchColumn();

  $countWB = $pdo->prepare("SELECT COUNT(*) FROM whiteboards WHERE project_id=? AND created_by=?");
  $countWB->execute([$id, $uid]); $wbCount = (int)$countWB->fetchColumn();

  ob_start(); ?>
  <h2 style="display:flex;align-items:center;gap:10px">
    <span style="display:inline-block;width:14px;height:14px;border-radius:4px;background:<?= htmlspecialchars($p['color'] ?? '#6c5ce7') ?>;border:1px solid #232842"></span>
    <?= htmlspecialchars($p['title']) ?>
  </h2>
  <p class="muted">
    <?= htmlspecialchars($p['course_code'] ?? '') ?>
    <?= !empty($p['course_code']) && !empty($p['description']) ? '•' : '' ?>
    <?= htmlspecialchars($p['description'] ?? '') ?>
  </p>
  <a class="btn-sm" href="index.php?route=projects.delete&id=<?= (int)$p['id'] ?>" style="color:#f87171">Delete</a>


  <style>
    .grid { display:grid; grid-template-columns: repeat(auto-fit,minmax(280px,1fr)); gap:16px; margin-top:14px; }
    .card { border:1px solid #232842; border-radius:12px; padding:12px; background:#0b0e1a; }
    .card h3 { margin:0 0 10px; font-size:16px; }
    .muted { color:#9aa3b2; font-size:12px; }
    .list { list-style:none; padding:0; margin:0; }
    .list li { padding:8px 0; border-top:1px dashed #232842; }
    .list li:first-child { border-top:0; }
    .thumb { width:100%; max-height:140px; object-fit:cover; border-radius:8px; border:1px solid #232842; background:#0e1120; }
    .badge { display:inline-block; padding:2px 6px; border-radius:999px; font-size:11px; border:1px solid #232842; color:#9aa3b2; }
    .row { display:flex; align-items:center; justify-content:space-between; gap:8px; }
    .btn-sm { display:inline-block; padding:6px 10px; border-radius:8px; border:1px solid #232842; background:#0e1120; color:#e5e7eb; text-decoration:none; }
  </style>

  <div class="grid">
    <!-- TODOS -->
    <div class="card">
      <div class="row">
        <h3>Todos <span class="badge"><?= $todoCount ?></span></h3>
        <a class="btn-sm" href="index.php?route=todos.list&project_id=<?= (int)$p['id'] ?>">View all</a>
      </div>
      <ul class="list">
        <?php foreach ($todos as $t): ?>
          <li>
            <a href="index.php?route=todos.view&id=<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['title']) ?></a>
            <div class="muted">
              <?= htmlspecialchars($t['status']) ?>
              <?php if ($t['priority']): ?> • P<?= (int)$t['priority'] ?><?php endif; ?>
              <?php if (!empty($t['due_date'])): ?> • due <?= htmlspecialchars($t['due_date']) ?><?php endif; ?>
            </div>
          </li>
        <?php endforeach; if (!$todos): ?><li class="muted">No todos yet.</li><?php endif; ?>
      </ul>
    </div>

    <!-- NOTES -->
    <div class="card">
      <div class="row">
        <h3>Notes <span class="badge"><?= $noteCount ?></span></h3>
        <a class="btn-sm" href="index.php?route=notes.list&project_id=<?= (int)$p['id'] ?>">View all</a>
      </div>
      <ul class="list">
        <?php foreach ($notes as $n): ?>
          <li>
            <a href="index.php?route=notes.view&id=<?= (int)$n['id'] ?>"><?= htmlspecialchars($n['title']) ?></a>
            <div class="muted"><?= !empty($n['is_pinned']) ? 'Pinned • ' : '' ?>updated <?= htmlspecialchars($n['updated_at'] ?? '') ?></div>
          </li>
        <?php endforeach; if (!$notes): ?><li class="muted">No notes yet.</li><?php endif; ?>
      </ul>
    </div>

    <!-- EVENTS -->
    <div class="card">
      <div class="row">
        <h3>Events <span class="badge"><?= $eventCount ?></span></h3>
        <a class="btn-sm" href="index.php?route=events.calendar&project_id=<?= (int)$p['id'] ?>">Open calendar</a>
      </div>
      <ul class="list">
        <?php foreach ($events as $e): ?>
          <li>
            <a href="index.php?route=events.view&id=<?= (int)$e['id'] ?>"><?= htmlspecialchars($e['title']) ?></a>
            <div class="muted">
              <?= htmlspecialchars($e['all_day'] ? 'All day' : ($e['start_datetime'] ?? '')) ?>
              <?php if (!empty($e['location'])): ?> • <?= htmlspecialchars($e['location']) ?><?php endif; ?>
            </div>
          </li>
        <?php endforeach; if (!$events): ?><li class="muted">No events yet.</li><?php endif; ?>
      </ul>
    </div>

    <!-- MIND MAPS -->
    <div class="card">
      <div class="row">
        <h3>Mind maps <span class="badge"><?= $mmCount ?></span></h3>
        <a class="btn-sm" href="index.php?route=mindmaps.list&project_id=<?= (int)$p['id'] ?>">View all</a>
      </div>
      <ul class="list">
        <?php foreach ($mindmaps as $m): ?>
          <li>
            <a href="index.php?route=mindmaps.view&id=<?= (int)$m['id'] ?>"><?= htmlspecialchars($m['title']) ?></a>
            <div class="muted">updated <?= htmlspecialchars($m['updated_at'] ?? '') ?></div>
            <?php if (!empty($m['thumbnail_path'])): ?>
              <div style="margin-top:6px">
                <img class="thumb" src="<?= htmlspecialchars($m['thumbnail_path']) ?>" alt="Mind map thumbnail">
              </div>
            <?php endif; ?>
          </li>
        <?php endforeach; if (!$mindmaps): ?><li class="muted">No mind maps yet.</li><?php endif; ?>
      </ul>
    </div>

    <!-- WHITEBOARDS -->
    <div class="card">
      <div class="row">
        <h3>Whiteboards <span class="badge"><?= $wbCount ?></span></h3>
        <a class="btn-sm" href="index.php?route=whiteboards.list&project_id=<?= (int)$p['id'] ?>">View all</a>
      </div>
      <ul class="list">
        <?php foreach ($whiteboards as $w): ?>
          <li>
            <a href="index.php?route=whiteboards.view&id=<?= (int)$w['id'] ?>"><?= htmlspecialchars($w['title']) ?></a>
            <div class="muted">updated <?= htmlspecialchars($w['updated_at'] ?? '') ?></div>
            <?php if (!empty($w['thumbnail_path'])): ?>
              <div style="margin-top:6px">
                <img class="thumb" src="<?= htmlspecialchars($w['thumbnail_path']) ?>" alt="Whiteboard thumbnail">
              </div>
            <?php endif; ?>
          </li>
        <?php endforeach; if (!$whiteboards): ?><li class="muted">No whiteboards yet.</li><?php endif; ?>
      </ul>
    </div>
  </div>
  <?php
  $content = ob_get_clean();
  $title = 'Project Overview';
  require __DIR__ . '/../Views/layout.php';
  exit;
}

http_response_code(404);
echo 'Unknown route';

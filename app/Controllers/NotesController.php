<?php
declare(strict_types=1);

require_once __DIR__ . '/../Lib/db.php';
require_once __DIR__ . '/../Middleware/Auth.php';

require_login();
verify_csrf();

$pdo = getDB();
$uid = (int) $_SESSION['user_id'];
$route = $_GET['route'] ?? 'notes.list';

/** Sidebar projects */
$projStmt = $pdo->prepare("
  SELECT id, title AS name, color
  FROM projects
  WHERE owner_id = ?
  ORDER BY created_at DESC
  LIMIT 50
");
$projStmt->execute([$uid]);
$projects = $projStmt->fetchAll();

/** Utilities */
function ensure_note_owner(PDO $pdo, int $noteId, int $uid): array
{
  $q = $pdo->prepare("
    SELECT n.*, p.title AS project_title
    FROM notes n
    JOIN projects p ON p.id = n.project_id
    WHERE n.id = ? AND n.created_by = ?
  ");
  $q->execute([$noteId, $uid]);
  $note = $q->fetch();
  if (!$note) {
    http_response_code(404);
    exit('Note not found');
  }
  return $note;
}

/** LIST */
if ($route === 'notes.list') {
  $stmt = $pdo->prepare("
    SELECT n.id, n.title, n.is_pinned, n.updated_at,
           p.title AS project
    FROM notes n
    JOIN projects p ON p.id = n.project_id
    WHERE n.created_by = ?
    ORDER BY n.is_pinned DESC, n.updated_at DESC, n.id DESC
  ");
  $stmt->execute([$uid]);
  $notes = $stmt->fetchAll();

  ob_start(); ?>
  <h2>Notes</h2>
  <p><a class="btn" href="index.php?route=notes.new">+ New Note</a></p>
  <form method="get" action="index.php" style="margin:8px 0; max-width:520px">
    <input type="hidden" name="route" value="notes.search" />
    <input name="q" placeholder="Search notes..." style="width:100%;padding:10px" />
  </form>
  <ul class="list">
    <?php foreach ($notes as $n): ?>
      <li>
        <a href="index.php?route=notes.view&id=<?= (int) $n['id'] ?>">
          <?= $n['is_pinned'] ? '📌 ' : '' ?>     <?= htmlspecialchars($n['title']) ?>
        </a>
        <div class="muted">
          <?= htmlspecialchars($n['project']) ?> • updated <?= htmlspecialchars($n['updated_at']) ?>
        </div>
      </li>
    <?php endforeach;
    if (!$notes): ?>
      <li class="muted">No notes yet.</li>
    <?php endif; ?>
  </ul>
  <script>
    const searchInput = document.querySelector('input[name="q"]');
    const resultsList = document.querySelector('ul.list');
    let debounceTimer;

    searchInput.addEventListener('input', function () {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => {
        const q = this.value;
        fetch('index.php?route=notes.search&ajax=1&q=' + encodeURIComponent(q))
          .then(r => r.text())
          .then(html => resultsList.innerHTML = html);
      }, 300);
    });
  </script>
  <?php
  $content = ob_get_clean();
  $title = 'Notes';
  require __DIR__ . '/../Views/layout.php';
  exit;
}

/** NEW */
if ($route === 'notes.new') {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = (int) ($_POST['project_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if ($project_id && $title !== '' && $content !== '') {
      $ins = $pdo->prepare("
        INSERT INTO notes (project_id, title, content, is_pinned, created_by)
        VALUES (?,?,?,?,?)
      ");
      $ins->execute([$project_id, $title, $content, 0, $uid]);
      header('Location: index.php?route=notes.list');
      exit;
    }
    $err = 'Project, title, and content are required.';
  }

  ob_start(); ?>
  <h2>New Note</h2>
  <?php if (!empty($err)): ?>
    <p style="color:#f87171"><?= htmlspecialchars($err) ?></p><?php endif; ?>
  <form method="post" style="max-width:720px">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <label>Project</label>
    <select name="project_id" required style="width:100%;padding:10px;margin:6px 0">
      <option value="">-- Select project --</option>
      <?php foreach ($projects as $p): ?>
        <option value="<?= (int) $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
      <?php endforeach; ?>
    </select>

    <label>Title</label>
    <input name="title" required style="width:100%;padding:10px;margin:6px 0" />

    <label>Content</label>
    <textarea name="content" rows="10" required style="width:100%;padding:10px;margin:6px 0"></textarea>

    <button class="btn" type="submit">Create</button>
  </form>
  <?php
  $content = ob_get_clean();
  $title = 'New Note';
  require __DIR__ . '/../Views/layout.php';
  exit;
}

/** VIEW */
if ($route === 'notes.view') {
  $id = (int) ($_GET['id'] ?? 0);
  $note = ensure_note_owner($pdo, $id, $uid);

  ob_start(); ?>
  <h2><?= htmlspecialchars($note['title']) ?></h2>
  <p class="muted">Project: <?= htmlspecialchars($note['project_title']) ?> • Updated:
    <?= htmlspecialchars($note['updated_at']) ?>   <?= $note['is_pinned'] ? '• 📌 Pinned' : '' ?>
  </p>
  <div style="white-space:pre-wrap; line-height:1.5"><?= htmlspecialchars($note['content']) ?></div>
  <p style="margin-top:12px">
  <form method="post" action="index.php?route=notes.pin&id=<?= (int) $note['id'] ?>" style="display:inline">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <button class="btn" type="submit"><?= $note['is_pinned'] ? 'Unpin' : 'Pin' ?></button>
  </form>
  <a class="btn" href="index.php?route=notes.edit&id=<?= (int) $note['id'] ?>">Edit</a>
  <form method="post" action="index.php?route=notes.delete&id=<?= (int) $note['id'] ?>" style="display:inline"
    onsubmit="return confirm('Delete this note?');">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <button class="btn" type="submit">Delete</button>
  </form>
  <a class="btn" href="index.php?route=notes.list">Back</a>
  </p>
  <?php
  $content = ob_get_clean();
  $title = 'Note';
  require __DIR__ . '/../Views/layout.php';
  exit;
}

/** EDIT */
if ($route === 'notes.edit') {
  $id = (int) ($_GET['id'] ?? 0);
  $note = ensure_note_owner($pdo, $id, $uid);

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = (int) ($_POST['project_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    if ($project_id && $title !== '' && $content !== '') {
      $upd = $pdo->prepare("
        UPDATE notes
        SET project_id=?, title=?, content=?
        WHERE id=? AND created_by=?
      ");
      $upd->execute([$project_id, $title, $content, $id, $uid]);
      header('Location: index.php?route=notes.view&id=' . $id);
      exit;
    }
    $err = 'Project, title, and content are required.';
  }

  ob_start(); ?>
  <h2>Edit Note</h2>
  <?php if (!empty($err)): ?>
    <p style="color:#f87171"><?= htmlspecialchars($err) ?></p><?php endif; ?>
  <form method="post" style="max-width:720px">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <label>Project</label>
    <select name="project_id" required style="width:100%;padding:10px;margin:6px 0">
      <?php foreach ($projects as $p): ?>
        <option value="<?= (int) $p['id'] ?>" <?= (int) $p['id'] === (int) $note['project_id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($p['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label>Title</label>
    <input name="title" required value="<?= htmlspecialchars($note['title']) ?>"
      style="width:100%;padding:10px;margin:6px 0" />

    <label>Content</label>
    <textarea name="content" rows="10" required
      style="width:100%;padding:10px;margin:6px 0"><?= htmlspecialchars($note['content']) ?></textarea>

    <button class="btn" type="submit">Save</button>
  </form>
  <?php
  $content = ob_get_clean();
  $title = 'Edit Note';
  require __DIR__ . '/../Views/layout.php';
  exit;
}

/** DELETE */
if ($route === 'notes.delete') {
  $id = (int) ($_GET['id'] ?? 0);
  $chk = $pdo->prepare("SELECT id FROM notes WHERE id=? AND created_by=?");
  $chk->execute([$id, $uid]);
  if ($chk->fetch()) {
    $del = $pdo->prepare("DELETE FROM notes WHERE id=? AND created_by=?");
    $del->execute([$id, $uid]);
  }
  header('Location: index.php?route=notes.list');
  exit;
}

/** PIN */
if ($route === 'notes.pin') {
  $id = (int) ($_GET['id'] ?? 0);
  $note = ensure_note_owner($pdo, $id, $uid);
  $next = $note['is_pinned'] ? 0 : 1;
  $upd = $pdo->prepare("UPDATE notes SET is_pinned=? WHERE id=? AND created_by=?");
  $upd->execute([$next, $id, $uid]);
  header('Location: index.php?route=notes.view&id=' . $id);
  exit;
}

/** SEARCH */
if ($route === 'notes.search') {
  $q = trim($_GET['q'] ?? '');
  $isAjax = isset($_GET['ajax']);
  $notes = [];

  if ($q !== '') {
    $like = '%' . $q . '%';
    $stmt = $pdo->prepare("
      SELECT n.id, n.title, n.is_pinned, n.updated_at, p.title AS project
      FROM notes n
      JOIN projects p ON p.id = n.project_id
      WHERE n.created_by = ? AND (n.title LIKE ? OR n.content LIKE ?)
      ORDER BY n.updated_at DESC
      LIMIT 100
    ");
    $stmt->execute([$uid, $like, $like]);
    $notes = $stmt->fetchAll();
  } else {
    // fetch all notes
    $stmt = $pdo->prepare("
      SELECT n.id, n.title, n.is_pinned, n.updated_at,
             p.title AS project
      FROM notes n
      JOIN projects p ON p.id = n.project_id
      WHERE n.created_by = ?
      ORDER BY n.is_pinned DESC, n.updated_at DESC, n.id DESC
    ");
    $stmt->execute([$uid]);
    $notes = $stmt->fetchAll();
  }

  if ($isAjax) {
    foreach ($notes as $n): ?>
      <li>
        <a href="index.php?route=notes.view&id=<?= (int) $n['id'] ?>">
          <?= $n['is_pinned'] ? '📌 ' : '' ?>       <?= htmlspecialchars($n['title']) ?>
        </a>
        <div class="muted"><?= htmlspecialchars($n['project']) ?> • updated <?= htmlspecialchars($n['updated_at']) ?></div>
      </li>
    <?php endforeach;
    if (!$notes): ?>
      <li class="muted">No results.</li>
    <?php endif;
    exit;
  }

  ob_start(); ?>
  <h2>Search Notes</h2>
  <form method="get" action="index.php" style="margin:8px 0; max-width:520px">
    <input type="hidden" name="route" value="notes.search" />
    <input name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search notes..." style="width:100%;padding:10px" />
  </form>
  <ul class="list">
    <?php foreach ($notes as $n): ?>
      <li>
        <a href="index.php?route=notes.view&id=<?= (int) $n['id'] ?>">
          <?= $n['is_pinned'] ? '📌 ' : '' ?>     <?= htmlspecialchars($n['title']) ?>
        </a>
        <div class="muted"><?= htmlspecialchars($n['project']) ?> • updated <?= htmlspecialchars($n['updated_at']) ?></div>
      </li>
    <?php endforeach;
    if (!$notes): ?>
      <li class="muted">No results.</li>
    <?php endif; ?>
  </ul>
  <script>
    const searchInput = document.querySelector('input[name="q"]');
    const resultsList = document.querySelector('ul.list');
    let debounceTimer;

    searchInput.addEventListener('input', function () {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => {
        const q = this.value;
        fetch('index.php?route=notes.search&ajax=1&q=' + encodeURIComponent(q))
          .then(r => r.text())
          .then(html => resultsList.innerHTML = html);
      }, 300);
    });
  </script>
  <?php
  $content = ob_get_clean();
  $title = 'Search Notes';
  require __DIR__ . '/../Views/layout.php';
  exit;
}

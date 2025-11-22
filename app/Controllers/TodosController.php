<?php
declare(strict_types=1);

require_once __DIR__ . '/../Lib/db.php';
require_once __DIR__ . '/../Middleware/Auth.php';

require_login();
verify_csrf();
$pdo = getDB();
$uid = (int) $_SESSION['user_id'];
$route = $_GET['route'] ?? 'todos.list';

// Sidebar projects
$projects = $pdo->prepare("SELECT id, title AS name, color FROM projects WHERE owner_id=? ORDER BY created_at DESC LIMIT 50");
$projects->execute([$uid]);
$projects = $projects->fetchAll();

if ($route === 'todos.list') {
  $stmt = $pdo->prepare("
    SELECT t.id, t.title, t.status, t.priority, t.due_date, p.title AS project
    FROM todos t
    JOIN projects p ON p.id = t.project_id
    WHERE t.created_by = ?
    ORDER BY (t.status='done'), IFNULL(t.due_date,'9999-12-31'), t.priority DESC, t.id DESC
  ");
  $stmt->execute([$uid]);
  $todos = $stmt->fetchAll();

  ob_start(); ?>
  <h2>To-dos</h2>
  <p><a class="btn" href="index.php?route=todos.new">+ Add To-do</a></p>
  <ul class="list">
    <?php foreach ($todos as $t): ?>
      <li>
        <a href="index.php?route=todos.view&id=<?= (int) $t['id'] ?>">
          <?= htmlspecialchars($t['title']) ?>
        </a>
        <div class="muted">
          <?= htmlspecialchars($t['project']) ?>
          <?= $t['due_date'] ? ' • due ' . htmlspecialchars($t['due_date']) : '' ?>
          • status <?= htmlspecialchars($t['status']) ?> • priority <?= (int) $t['priority'] ?>
        </div>
        <div style="margin-top:6px">
          <form method="post" action="index.php?route=todos.toggle&id=<?= (int) $t['id'] ?>" style="display:inline">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <button class="btn" type="submit"><?= $t['status'] === 'done' ? 'Mark todo' : 'Mark done' ?></button>
          </form>
          <form method="post" action="index.php?route=todos.delete&id=<?= (int) $t['id'] ?>" style="display:inline"
            onsubmit="return confirm('Delete this to-do?')">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <button class="btn" type="submit">Delete</button>
          </form>
        </div>
      </li>
    <?php endforeach;
    if (!$todos): ?>
      <li class="muted">No to-dos yet.</li><?php endif; ?>
  </ul>
  <?php
  $content = ob_get_clean();
  $title = 'To-dos';
  require __DIR__ . '/../Views/layout.php';
  exit;
}

if ($route === 'todos.new') {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = (int) ($_POST['project_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $priority = (int) ($_POST['priority'] ?? 0);
    $due_date = $_POST['due_date'] !== '' ? $_POST['due_date'] : null;

    if ($project_id && $title !== '') {
      $ins = $pdo->prepare("INSERT INTO todos (project_id, title, status, priority, due_date, created_by) VALUES (?,?,?,?,?,?)");
      $ins->execute([$project_id, $title, 'todo', $priority, $due_date, $uid]);
      header('Location: index.php?route=todos.list');
      exit;
    }
    $err = 'Project and title are required.';
  }

  ob_start(); ?>
  <h2>New To-do</h2>
  <?php if (!empty($err)): ?>
    <p style="color:#f87171"><?= htmlspecialchars($err) ?></p><?php endif; ?>
  <form method="post" style="max-width:520px">
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

    <label>Priority (0–5)</label>
    <input name="priority" type="number" min="0" max="5" value="0" style="width:100%;padding:10px;margin:6px 0" />

    <label>Due date (optional)</label>
    <input name="due_date" type="date" style="width:100%;padding:10px;margin:6px 0" />

    <button class="btn" type="submit">Create</button>
  </form>
  <?php
  $content = ob_get_clean();
  $title = 'New To-do';
  require __DIR__ . '/../Views/layout.php';
  exit;
}

if ($route === 'todos.view') {
  $id = (int) ($_GET['id'] ?? 0);
  $stmt = $pdo->prepare("
    SELECT t.*, p.title AS project_title
    FROM todos t JOIN projects p ON p.id=t.project_id
    WHERE t.id=? AND t.created_by=?
  ");
  $stmt->execute([$id, $uid]);
  $todo = $stmt->fetch();
  if (!$todo) {
    http_response_code(404);
    exit('Not found');
  }

  ob_start(); ?>
  <h2><?= htmlspecialchars($todo['title']) ?></h2>
  <p class="muted">Project: <?= htmlspecialchars($todo['project_title']) ?></p>
  <p>Status: <?= htmlspecialchars($todo['status']) ?> • Priority: <?= (int) $todo['priority'] ?>
    <?= $todo['due_date'] ? '• Due ' . $todo['due_date'] : '' ?></p>
  <p><?= nl2br(htmlspecialchars((string) $todo['description'])) ?></p>
  <p><a class="btn" href="index.php?route=todos.list">Back</a></p>
  <?php
  $content = ob_get_clean();
  $title = 'To-do';
  require __DIR__ . '/../Views/layout.php';
  exit;
}

if ($route === 'todos.toggle') {
  $id = (int) ($_GET['id'] ?? 0);
  $stmt = $pdo->prepare("SELECT status FROM todos WHERE id=? AND created_by=?");
  $stmt->execute([$id, $uid]);
  $row = $stmt->fetch();
  if ($row) {
    $next = ($row['status'] === 'done') ? 'todo' : 'done';
    $upd = $pdo->prepare("UPDATE todos SET status=? WHERE id=? AND created_by=?");
    $upd->execute([$next, $id, $uid]);
  }
  header('Location: index.php?route=todos.list');
  exit;
}

if ($route === 'todos.delete') {
  $id = (int) ($_GET['id'] ?? 0);
  $del = $pdo->prepare("DELETE FROM todos WHERE id=? AND created_by=?");
  $del->execute([$id, $uid]);
  header('Location: index.php?route=todos.list');
  exit;
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/../Lib/db.php';
require_once __DIR__ . '/../Middleware/Auth.php';

require_login();
$pdo = getDB();
$uid = (int) $_SESSION['user_id'];

/** Sidebar projects */
$projects = [];
$projectsStmt = $pdo->prepare("
  SELECT p.id, p.title AS name, p.color
  FROM projects p
  LEFT JOIN project_members m ON m.project_id = p.id
  WHERE p.owner_id = ? OR m.user_id = ?
  GROUP BY p.id
  ORDER BY p.created_at DESC
  LIMIT 20
");
$projectsStmt->execute([$uid, $uid]);
$projects = $projectsStmt->fetchAll();

/** counts */
$counts = [
  'todos_open' => 0,
  'notes' => 0,
  'mindmaps' => 0,
  'whiteboards' => 0,
  'events_today' => 0
];

$counts['todos_open'] = (int) $pdo->query("
  SELECT COUNT(*) FROM todos t WHERE t.created_by = {$uid} AND t.status <> 'done'
")->fetchColumn();

$counts['notes'] = (int) $pdo->query("
  SELECT COUNT(*) FROM notes n WHERE n.created_by = {$uid}
")->fetchColumn();

$counts['mindmaps'] = (int) $pdo->query("
  SELECT COUNT(*) FROM mindmaps m WHERE m.created_by = {$uid}
")->fetchColumn();

$counts['whiteboards'] = (int) $pdo->query("
  SELECT COUNT(*) FROM whiteboards w WHERE w.created_by = {$uid}
")->fetchColumn();

$counts['events_today'] = (int) $pdo->query("
  SELECT COUNT(*) FROM events e
  WHERE e.created_by = {$uid} AND DATE(e.start_datetime) = CURDATE()
")->fetchColumn();

/** top 5 lists */
$topTodos = $pdo->query("
  SELECT t.id, t.title, t.status, t.due_date, p.title AS project
  FROM todos t
  JOIN projects p ON p.id = t.project_id
  WHERE t.created_by = {$uid} AND t.status <> 'done'
  ORDER BY IFNULL(t.due_date, '9999-12-31') ASC, t.priority DESC, t.id DESC
  LIMIT 5
")->fetchAll();

$topNotes = $pdo->query("
  SELECT n.id, n.title, p.title AS project
  FROM notes n
  JOIN projects p ON p.id = n.project_id
  WHERE n.created_by = {$uid}
  ORDER BY n.updated_at DESC, n.created_at DESC
  LIMIT 5
")->fetchAll();

$topMindmaps = $pdo->query("
  SELECT m.id, m.title, p.title AS project
  FROM mindmaps m
  JOIN projects p ON p.id = m.project_id
  WHERE m.created_by = {$uid}
  ORDER BY m.updated_at DESC, m.created_at DESC
  LIMIT 5
")->fetchAll();

$topWhiteboards = $pdo->query("
  SELECT w.id, w.title, p.title AS project
  FROM whiteboards w
  JOIN projects p ON p.id = w.project_id
  WHERE w.created_by = {$uid}
  ORDER BY w.updated_at DESC, w.created_at DESC
  LIMIT 5
")->fetchAll();

$topEvents = $pdo->query("
  SELECT id, title, DATE_FORMAT(start_datetime,'%b %e %H:%i') AS when_str
  FROM events e
  WHERE e.created_by = {$uid} AND e.start_datetime >= NOW()
  ORDER BY e.start_datetime ASC
  LIMIT 5
")->fetchAll();

$lastVisitTs = $_SESSION['last_visit'] ?? null;
$lastVisitFormatted = null;

if (!empty($lastVisitTs)) {
    $dt = new DateTime('@' . (int)$lastVisitTs);  
    $dt->setTimezone(new DateTimeZone('America/Chicago'));
    $lastVisitFormatted = $dt->format('Y-m-d g:i A'); // Customize format if you want
}
/** render */
ob_start();
?>

<div class="topbar">
  <h1 style="margin:0">Dashboard</h1>
  <div class="muted">
    Hello, <?= htmlspecialchars($_SESSION['name'] ?? 'Student') ?>
    <?php if (!empty($lastVisitFormatted)): ?>
      · Last visit: <?= htmlspecialchars($lastVisitFormatted) ?>
    <?php else: ?>
      · Welcome! This looks like your first visit.
    <?php endif; ?>
  </div>
</div>


<div class="grid">
  <div class="card">
    <h3>To-dos <a class="see-more" href="index.php?route=todos.list">See all →</a></h3>
    <ul class="list">
      <?php foreach ($topTodos as $t): ?>
        <li>
          <a href="index.php?route=todos.view&id=<?= (int) $t['id'] ?>">
            <?= htmlspecialchars($t['title']) ?>
          </a>
          <div class="muted">
            <?= $t['project'] ? '• ' . htmlspecialchars($t['project']) : '' ?>
            <?= $t['due_date'] ? ' • due ' . htmlspecialchars($t['due_date']) : '' ?>
          </div>
        </li>
      <?php endforeach;
      if (!$topTodos): ?>
        <li class="muted">No open to-dos.</li>
      <?php endif; ?>
    </ul>
    <p style="margin-top:10px"><a class="btn" href="index.php?route=todos.new">+ Add To-do</a></p>
  </div>

  <div class="card">
    <h3>Notes <a class="see-more" href="index.php?route=notes.list">See all →</a></h3>
    <ul class="list">
      <?php foreach ($topNotes as $n): ?>
        <li>
          <a href="index.php?route=notes.view&id=<?= (int) $n['id'] ?>"><?= htmlspecialchars($n['title']) ?></a>
          <div class="muted"><?= $n['project'] ? '• ' . htmlspecialchars($n['project']) : '' ?></div>
        </li>
      <?php endforeach;
      if (!$topNotes): ?>
        <li class="muted">No notes yet.</li><?php endif; ?>
    </ul>
    <p style="margin-top:10px"><a class="btn" href="index.php?route=notes.new">+ New Note</a></p>
  </div>

  <div class="card">
    <h3>Mind maps <a class="see-more" href="index.php?route=mindmaps.list">See all →</a></h3>
    <ul class="list">
      <?php foreach ($topMindmaps as $m): ?>
        <li>
          <a href="index.php?route=mindmaps.view&id=<?= (int) $m['id'] ?>"><?= htmlspecialchars($m['title']) ?></a>
          <div class="muted"><?= $m['project'] ? '• ' . htmlspecialchars($m['project']) : '' ?></div>
        </li>
      <?php endforeach;
      if (!$topMindmaps): ?>
        <li class="muted">No mind maps yet.</li><?php endif; ?>
    </ul>
    <p style="margin-top:10px"><a class="btn" href="index.php?route=mindmaps.new">+ New Mind map</a></p>
  </div>

  <div class="card">
    <h3>Whiteboards <a class="see-more" href="index.php?route=whiteboards.list">See all →</a></h3>
    <ul class="list">
      <?php foreach ($topWhiteboards as $w): ?>
        <li>
          <a href="index.php?route=whiteboards.view&id=<?= (int) $w['id'] ?>"><?= htmlspecialchars($w['title']) ?></a>
          <div class="muted"><?= $w['project'] ? '• ' . htmlspecialchars($w['project']) : '' ?></div>
        </li>
      <?php endforeach;
      if (!$topWhiteboards): ?>
        <li class="muted">No whiteboards yet.</li><?php endif; ?>
    </ul>
    <p style="margin-top:10px"><a class="btn" href="index.php?route=whiteboards.new">+ New Whiteboard</a></p>
  </div>

  <div class="card">
    <h3>Upcoming (Calendar) <a class="see-more" href="index.php?route=events.calendar">Open calendar →</a></h3>
    <ul class="list">
      <?php foreach ($topEvents as $e): ?>
        <li>
          <a href="index.php?route=events.view&id=<?= (int) $e['id'] ?>"><?= htmlspecialchars($e['title']) ?></a>
          <div class="muted">• <?= htmlspecialchars($e['when_str']) ?></div>
        </li>
      <?php endforeach;
      if (!$topEvents): ?>
        <li class="muted">No upcoming events.</li><?php endif; ?>
    </ul>
    <p style="margin-top:10px"><a class="btn" href="index.php?route=events.new">+ Add Event</a></p>
  </div>

  <div class="card">
    <h3>Stats</h3>
    <div class="muted">Open to-dos: <?= $counts['todos_open'] ?> • Notes: <?= $counts['notes'] ?> • Mind maps:
      <?= $counts['mindmaps'] ?> • Whiteboards: <?= $counts['whiteboards'] ?> • Today’s events:
      <?= $counts['events_today'] ?></div>
  </div>
</div>

<?php if (($_SESSION['role'] ?? '') !== 'admin'): ?>
  <div class="card" style="border-color:#f87171">
    <h3 style="color:#f87171">Danger zone</h3>
    <p class="muted">
      Once you delete your account, all your projects, notes, to-dos, and mind maps will be permanently removed.
    </p>
    <form action="index.php?route=user.delete" method="POST"
      onsubmit="return confirm('Are you sure you want to permanently delete your account? This action cannot be undone.');">
      <button type="submit" class="btn" style="background:linear-gradient(180deg,#dc2626,#b91c1c);box-shadow:none;">
        Delete My Account
      </button>
    </form>
  </div>
<?php endif; ?>

<?php
$content = ob_get_clean();

$title = 'Dashboard • BrainBow';
require __DIR__ . '/../Views/layout.php';

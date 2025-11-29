<?php
declare(strict_types=1);

require_once __DIR__ . '/../Lib/db.php';
require_once __DIR__ . '/../Middleware/Auth.php';

require_login();
verify_csrf();

$pdo = getDB();
$uid = (int)($_SESSION['user_id'] ?? 0);
$route = $_GET['route'] ?? 'events.list';

/** Sidebar projects*/
$projStmt = $pdo->prepare("
  SELECT id, title AS name, color
  FROM projects
  WHERE owner_id = ?
  ORDER BY created_at DESC
  LIMIT 50
");
$projStmt->execute([$uid]);
$projects = $projStmt->fetchAll(PDO::FETCH_ASSOC);

/** Helpers */
function ensure_event_owner(PDO $pdo, int $eventId, int $uid): array {
  $q = $pdo->prepare("
    SELECT e.*, p.title AS project_title
    FROM events e
    JOIN projects p ON p.id = e.project_id
    WHERE e.id = ? AND e.created_by = ?
  ");
  $q->execute([$eventId, $uid]);
  $ev = $q->fetch(PDO::FETCH_ASSOC);
  if (!$ev) { http_response_code(404); exit('Event not found'); }
  return $ev;
}

/** LIST of upcoming events */
if ($route === 'events.list') {
  $stmt = $pdo->prepare("
    SELECT e.id, e.title, e.start_datetime, e.end_datetime, e.all_day,
           e.location, p.title AS project
    FROM events e
    JOIN projects p ON p.id = e.project_id
    WHERE e.created_by = ? AND e.start_datetime >= DATE_SUB(NOW(), INTERVAL 1 DAY)
    ORDER BY e.start_datetime ASC
    LIMIT 200
  ");
  $stmt->execute([$uid]);
  $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // group by (Y-m-d)
  $groups = [];
  foreach ($events as $e) {
    $day = substr($e['start_datetime'], 0, 10);
    $groups[$day][] = $e;
  }

  ob_start(); ?>
  <h2>Upcoming Events</h2>
  <p><a class="btn" href="index.php?route=events.new">+ Add Event</a>
     <a class="btn" href="index.php?route=events.calendar">Open calendar</a></p>

  <?php if (!$events): ?>
    <p class="muted">No upcoming events. Add one!</p>
  <?php else: ?>
    <?php foreach ($groups as $day => $rows): ?>
      <h3 style="margin-top:16px"><?= htmlspecialchars($day) ?></h3>
      <ul class="list">
        <?php foreach ($rows as $ev): ?>
          <li>
            <a href="index.php?route=events.view&id=<?= (int)$ev['id'] ?>">
              <?= htmlspecialchars($ev['title']) ?>
            </a>
            <div class="muted">
              <?= htmlspecialchars($ev['project']) ?>
              <?php if ($ev['all_day']): ?>
                • all-day
              <?php else: ?>
                • <?= htmlspecialchars(date('H:i', strtotime($ev['start_datetime']))) ?>
                <?php if (!empty($ev['end_datetime'])): ?>
                  – <?= htmlspecialchars(date('H:i', strtotime($ev['end_datetime']))) ?>
                <?php endif; ?>
              <?php endif; ?>
              <?= $ev['location'] ? ' • ' . htmlspecialchars($ev['location']) : '' ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endforeach; ?>
  <?php endif; ?>
  <?php
  $content = ob_get_clean();
  $title = 'Events';
  require __DIR__ . '/../Views/layout.php';
  exit;
}

/** CALENDAR */
if ($route === 'events.calendar') {
  // Inputs
  $projectFilter = (int)($_GET['project_id'] ?? 0);
  $year  = isset($_GET['year'])  ? max(1970, (int)$_GET['year'])  : (int)date('Y');
  $month = isset($_GET['month']) ? min(12, max(1, (int)$_GET['month'])) : (int)date('n');

  // grid 
  $firstOfMonth = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
  $startDow = (int)$firstOfMonth->format('w'); // 0=Sun
  $gridStart = $firstOfMonth->modify("-{$startDow} days");
  $gridEnd   = $gridStart->modify('+41 days'); // 6 weeks (42 cells) inclusive

  // events
  $sql = "
    SELECT e.id, e.title, e.start_datetime, e.end_datetime, e.all_day, e.location,
           e.project_id, p.title AS project, p.color
    FROM events e
    JOIN projects p ON p.id = e.project_id
    WHERE e.created_by = :uid
      AND e.start_datetime >= :start
      AND e.start_datetime <  :end
  ";
  $params = [
    ':uid' => $uid,
    ':start' => $gridStart->format('Y-m-d') . ' 00:00:00',
    ':end' => $gridEnd->modify('+1 day')->format('Y-m-d') . ' 00:00:00',
  ];
  if ($projectFilter) {
    $sql .= " AND e.project_id = :pid";
    $params[':pid'] = $projectFilter;
  }
  $sql .= " ORDER BY e.start_datetime ASC";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Bucket (Y-m-d)
  $byDay = [];
  foreach ($rows as $e) {
    $d = substr($e['start_datetime'], 0, 10);
    $byDay[$d][] = $e;
  }

  // Prev/Next
  $prev = $firstOfMonth->modify('-1 month');
  $next = $firstOfMonth->modify('+1 month');

  ob_start(); ?>
  <h2>Calendar — <?= htmlspecialchars($firstOfMonth->format('F Y')) ?></h2>

  <form method="get" style="display:flex;gap:8px;align-items:center;margin:8px 0">
    <input type="hidden" name="route" value="events.calendar">
    <select name="project_id" onchange="this.form.submit()" style="padding:6px 10px;border-radius:8px;border:1px solid #232842;background:#0e1120;color:#e5e7eb">
      <option value="0">All projects</option>
      <?php foreach ($projects as $p): ?>
        <option value="<?= (int)$p['id'] ?>" <?= $projectFilter===(int)$p['id']?'selected':'' ?>>
          <?= htmlspecialchars($p['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <a class="btn" href="index.php?route=events.new">+ Add Event</a>
    <span style="flex:1"></span>
    <a class="btn" href="index.php?route=events.calendar&year=<?= $prev->format('Y') ?>&month=<?= $prev->format('n') ?>&project_id=<?= (int)$projectFilter ?>">← <?= $prev->format('M Y') ?></a>
    <a class="btn" href="index.php?route=events.calendar&year=<?= $next->format('Y') ?>&month=<?= $next->format('n') ?>&project_id=<?= (int)$projectFilter ?>"><?= $next->format('M Y') ?> →</a>
  </form>

  <style>
    .cal { display:grid; grid-template-columns:repeat(7,1fr); gap:8px; }
    .cal .dow { text-align:center; font-weight:600; color:#9aa3b2; padding:6px 0; }
    .cal .cell { border:1px solid #232842; border-radius:12px; background:#0b0e1a; min-height:110px; padding:8px; }
    .cal .muted { color:#6b7280; }
    .pill { display:block; margin:6px 0 0; padding:4px 6px; border-radius:6px; font-size:12px; border:1px solid #232842; text-decoration:none; color:#e5e7eb; }
    .pill .time { color:#9aa3b2; }
    .cal .hdr { display:flex; align-items:center; justify-content:space-between; }
    .dot { width:10px; height:10px; border-radius:3px; display:inline-block; margin-right:6px; border:1px solid #232842; vertical-align:middle; }
  </style>

  <div class="cal">
    <?php
      $dows = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
      foreach ($dows as $d) echo '<div class="dow">'.$d.'</div>';

      $cursor = $gridStart;
      for ($i=0; $i<42; $i++) {
        $ymd = $cursor->format('Y-m-d');
        $isOtherMonth = $cursor->format('n') !== $firstOfMonth->format('n');
        echo '<div class="cell'.($isOtherMonth?' muted':'').'">';
        echo '<div class="hdr"><div>'.(int)$cursor->format('j').'</div></div>';

        if (!empty($byDay[$ymd])) {
          foreach ($byDay[$ymd] as $ev) {
            $time = $ev['all_day'] ? 'All day' : date('H:i', strtotime($ev['start_datetime']));
            $color = $ev['color'] ?? '#374151';
            echo '<a class="pill" href="index.php?route=events.view&id='.(int)$ev['id'].'">';
            echo '<span class="dot" style="background:'.htmlspecialchars($color).'"></span>';
            echo htmlspecialchars($ev['title']).' <span class="time">• '.htmlspecialchars($time).'</span>';
            echo '</a>';
          }
        } else {
          echo '<div class="muted" style="margin-top:6px;font-size:12px">—</div>';
        }

        echo '</div>';
        $cursor = $cursor->modify('+1 day');
      }
    ?>
  </div>
  <?php
  $content = ob_get_clean();
  $title = 'Calendar';
  require __DIR__ . '/../Views/layout.php';
  exit;
}

/** NEW */
if ($route === 'events.new') {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = (int)($_POST['project_id'] ?? 0);
    $title = trim((string)($_POST['title'] ?? ''));
    $all_day = isset($_POST['all_day']) ? 1 : 0;
    $location = trim((string)($_POST['location'] ?? ''));
    $date = trim((string)($_POST['date'] ?? ''));       // YYYY-MM-DD
    $start_time = trim((string)($_POST['start_time'] ?? '')); // HH:MM
    $end_time = trim((string)($_POST['end_time'] ?? ''));     // HH:MM
    $desc = trim((string)($_POST['description'] ?? ''));

    $start_datetime = null;
    $end_datetime = null;
    if ($date !== '') {
      if ($all_day) {
        $start_datetime = $date.' 00:00:00';
        $end_datetime   = $date.' 23:59:59';
      } else {
        if ($start_time !== '') $start_datetime = $date.' '.$start_time.':00';
        if ($end_time !== '')   $end_datetime   = $date.' '.$end_time.':00';
      }
    }

    if ($project_id && $title !== '' && $start_datetime) {
      $ins = $pdo->prepare("
        INSERT INTO events (project_id, title, description, start_datetime, end_datetime, all_day, location, created_by)
        VALUES (?,?,?,?,?,?,?,?)
      ");
      $ins->execute([$project_id, $title, $desc, $start_datetime, $end_datetime, $all_day, $location, $uid]);
      header('Location: index.php?route=events.calendar');
      exit;
    }
    $err = 'Project, title, and date(+start time unless all-day) are required.';
  }

  ob_start(); ?>
  <h2>New Event</h2>
  <?php if (!empty($err)): ?><p style="color:#f87171"><?= htmlspecialchars($err) ?></p><?php endif; ?>
  <form method="post" style="max-width:520px">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

    <label>Project</label>
    <select name="project_id" required style="width:100%;padding:10px;margin:6px 0">
      <option value="">-- Select project --</option>
      <?php foreach ($projects as $p): ?>
        <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
      <?php endforeach; ?>
    </select>

    <label>Title</label>
    <input name="title" required style="width:100%;padding:10px;margin:6px 0" />

    <label><input type="checkbox" name="all_day"
      onchange="document.querySelectorAll('.time-row').forEach(el=>el.style.display=this.checked?'none':'block')">
      All-day</label>

    <label>Date</label>
    <input name="date" type="date" required style="width:100%;padding:10px;margin:6px 0" />

    <div class="time-row">
      <label>Start time</label>
      <input name="start_time" type="time" style="width:100%;padding:10px;margin:6px 0" />
    </div>
    <div class="time-row">
      <label>End time (optional)</label>
      <input name="end_time" type="time" style="width:100%;padding:10px;margin:6px 0" />
    </div>

    <label>Location (optional)</label>
    <input name="location" style="width:100%;padding:10px;margin:6px 0" />

    <label>Description (optional)</label>
    <textarea name="description" rows="5" style="width:100%;padding:10px;margin:6px 0"></textarea>

    <button class="btn" type="submit">Create</button>
  </form>
  <script>
    (function(){ var cb=document.querySelector('input[name=all_day]');
      if (cb && cb.checked) { document.querySelectorAll('.time-row').forEach(el=>el.style.display='none'); }
    })();
  </script>
  <?php
  $content = ob_get_clean();
  $title = 'New Event';
  require __DIR__ . '/../Views/layout.php';
  exit;
}

/** VIEW */
if ($route === 'events.view') {
  $id = (int)($_GET['id'] ?? 0);
  $ev = ensure_event_owner($pdo, $id, $uid);

  ob_start(); ?>
  <h2><?= htmlspecialchars($ev['title']) ?></h2>
  <p class="muted">
    Project: <?= htmlspecialchars($ev['project_title']) ?> •
    <?= $ev['all_day'] ? 'All-day'
        : htmlspecialchars($ev['start_datetime']).($ev['end_datetime'] ? ' – '.htmlspecialchars($ev['end_datetime']) : '') ?>
    <?= $ev['location'] ? ' • ' . htmlspecialchars($ev['location']) : '' ?>
    • Updated: <?= htmlspecialchars($ev['updated_at']) ?>
  </p>
  <?php if (!empty($ev['description'])): ?>
    <div style="white-space:pre-wrap; line-height:1.5"><?= htmlspecialchars($ev['description']) ?></div>
  <?php endif; ?>

  <p style="margin-top:12px">
    <a class="btn" href="index.php?route=events.edit&id=<?= (int)$ev['id'] ?>">Edit</a>
    <form method="post" action="index.php?route=events.delete&id=<?= (int)$ev['id'] ?>" style="display:inline"
      onsubmit="return confirm('Delete this event?');">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <button class="btn" type="submit">Delete</button>
    </form>
    <a class="btn" href="index.php?route=events.calendar">Back to calendar</a>
  </p>
  <?php
  $content = ob_get_clean();
  $title = 'Event';
  require __DIR__ . '/../Views/layout.php';
  exit;
}

/** EDIT */
if ($route === 'events.edit') {
  $id = (int)($_GET['id'] ?? 0);
  $ev = ensure_event_owner($pdo, $id, $uid);

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = (int)($_POST['project_id'] ?? 0);
    $title = trim((string)($_POST['title'] ?? ''));
    $all_day = isset($_POST['all_day']) ? 1 : 0;
    $location = trim((string)($_POST['location'] ?? ''));
    $date = trim((string)($_POST['date'] ?? ''));
    $start_time = trim((string)($_POST['start_time'] ?? ''));
    $end_time = trim((string)($_POST['end_time'] ?? ''));
    $desc = trim((string)($_POST['description'] ?? ''));

    $start_datetime = null;
    $end_datetime = null;
    if ($date !== '') {
      if ($all_day) {
        $start_datetime = $date.' 00:00:00';
        $end_datetime   = $date.' 23:59:59';
      } else {
        if ($start_time !== '') $start_datetime = $date.' '.$start_time.':00';
        if ($end_time !== '')   $end_datetime   = $date.' '.$end_time.':00';
      }
    }

    if ($project_id && $title !== '' && $start_datetime) {
      $upd = $pdo->prepare("
        UPDATE events
        SET project_id=?, title=?, description=?, start_datetime=?, end_datetime=?, all_day=?, location=?
        WHERE id=? AND created_by=?
      ");
      $upd->execute([$project_id, $title, $desc, $start_datetime, $end_datetime, $all_day, $location, $id, $uid]);
      header('Location: index.php?route=events.view&id='.$id);
      exit;
    }
    $err = 'Project, title, and date(+start time unless all-day) are required.';
  }

  $date = substr($ev['start_datetime'], 0, 10);
  $start_time = $ev['all_day'] ? '' : substr($ev['start_datetime'], 11, 5);
  $end_time = $ev['all_day'] || empty($ev['end_datetime']) ? '' : substr($ev['end_datetime'], 11, 5);

  ob_start(); ?>
  <h2>Edit Event</h2>
  <?php if (!empty($err)): ?><p style="color:#f87171"><?= htmlspecialchars($err) ?></p><?php endif; ?>
  <form method="post" style="max-width:520px">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

    <label>Project</label>
    <select name="project_id" required style="width:100%;padding:10px;margin:6px 0">
      <?php foreach ($projects as $p): ?>
        <option value="<?= (int)$p['id'] ?>" <?= (int)$p['id']===(int)$ev['project_id']?'selected':'' ?>>
          <?= htmlspecialchars($p['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label>Title</label>
    <input name="title" required value="<?= htmlspecialchars($ev['title']) ?>" style="width:100%;padding:10px;margin:6px 0" />

    <label><input type="checkbox" name="all_day" <?= $ev['all_day'] ? 'checked' : '' ?>
      onchange="document.querySelectorAll('.time-row').forEach(el=>el.style.display=this.checked?'none':'block')">
      All-day</label>

    <label>Date</label>
    <input name="date" type="date" value="<?= htmlspecialchars($date) ?>" required style="width:100%;padding:10px;margin:6px 0" />

    <div class="time-row" style="<?= $ev['all_day'] ? 'display:none' : '' ?>">
      <label>Start time</label>
      <input name="start_time" type="time" value="<?= htmlspecialchars($start_time) ?>" style="width:100%;padding:10px;margin:6px 0" />
    </div>
    <div class="time-row" style="<?= $ev['all_day'] ? 'display:none' : '' ?>">
      <label>End time (optional)</label>
      <input name="end_time" type="time" value="<?= htmlspecialchars($end_time) ?>" style="width:100%;padding:10px;margin:6px 0" />
    </div>

    <label>Location (optional)</label>
    <input name="location" value="<?= htmlspecialchars((string)$ev['location']) ?>" style="width:100%;padding:10px;margin:6px 0" />

    <label>Description (optional)</label>
    <textarea name="description" rows="5" style="width:100%;padding:10px;margin:6px 0"><?= htmlspecialchars((string)$ev['description']) ?></textarea>

    <button class="btn" type="submit">Save</button>
  </form>
  <?php
  $content = ob_get_clean();
  $title = 'Edit Event';
  require __DIR__ . '/../Views/layout.php';
  exit;
}

/** DELETE */
if ($route === 'events.delete') {
  $id = (int)($_GET['id'] ?? 0);
  $chk = $pdo->prepare("SELECT id FROM events WHERE id=? AND created_by=?");
  $chk->execute([$id, $uid]);
  if ($chk->fetch(PDO::FETCH_ASSOC)) {
    $del = $pdo->prepare("DELETE FROM events WHERE id=? AND created_by=?");
    $del->execute([$id, $uid]);
  }
  header('Location: index.php?route=events.calendar');
  exit;
}

http_response_code(404);
echo 'Unknown route';

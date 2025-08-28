<?php
declare(strict_types=1);

// ---------- CHANGE THIS ----------
const SEED_KEY = '';
// ---------------------------------

session_start();
ini_set('display_errors','1'); error_reporting(E_ALL);

require __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

require_once __DIR__ . '/../app/Lib/db.php';

if (!isset($_GET['key']) || $_GET['key'] !== SEED_KEY) {
    http_response_code(403);
    echo "Forbidden. Add ?key=" . SEED_KEY . " to the URL (and change it first!).";
    exit;
}

$pdo = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name'] ?? 'Admin User');
    $email = trim($_POST['email'] ?? 'admin@brainbow.com');
    $pass  = trim($_POST['password'] ?? '');

    if ($name === '' || $email === '' || $pass === '') {
        $msg = 'All fields are required.';
    } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);

        $sql = "INSERT INTO users (name,email,password_hash,role,is_active,created_at)
                VALUES (:name,:email,:hash,'admin',1,NOW())
                ON DUPLICATE KEY UPDATE
                    name=VALUES(name),
                    password_hash=VALUES(password_hash),
                    role='admin',
                    is_active=1";
        $pdo->prepare($sql)->execute([
            ':name'  => $name,
            ':email' => $email,
            ':hash'  => $hash,
        ]);
        $msg = "✅ Admin seeded/updated for {$email}. You can now log in.";
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Seed Admin</title>
  <style>
    body { font-family: system-ui, -apple-system, Arial; padding: 24px; }
    form { max-width: 360px; }
    input, button { width: 100%; padding: 10px; margin: 6px 0; }
    .ok { color: #0a7; margin-top: 10px; }
    .err { color: #b00; margin-top: 10px; }
  </style>
</head>
<body>
  <h2>Seed / Update Admin</h2>
  <?php if ($msg): ?>
    <div class="<?= str_starts_with($msg,'✅') ? 'ok' : 'err' ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>
  <form method="post">
    <input type="text"     name="name"     value="Admin User" required>
    <input type="email"    name="email"    value="admin@brainbow.com" required>
    <input type="password" name="password" placeholder="New password" required>
    <button type="submit">Seed / Update Admin</button>
  </form>
  <p>After success, <strong>delete this file</strong> for safety.</p>
</body>
</html>

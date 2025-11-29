<?php
declare(strict_types=1);

require_once __DIR__ . '/../Lib/db.php';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $password = trim($_POST['password'] ?? '');

  if ($email !== '' && $password !== '') {
    $stmt = getDB()->prepare('SELECT id, name, role, password_hash FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
      session_regenerate_id(true);
      $_SESSION['user_id'] = (int) $user['id'];
      $_SESSION['name'] = $user['name'];
      $_SESSION['role'] = $user['role'];

      $pdo = getDB();
      $pdo->prepare("UPDATE users SET login_count = login_count + 1 WHERE id = ?")
        ->execute([$user['id']]);

      $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")
        ->execute([$user['id']]);


      // last visit cookie store in session
      $lastVisit = $_COOKIE['last_visit'] ?? null;
      $_SESSION['last_visit'] = $lastVisit;

      // cookie current timestamp
      setcookie(
        'last_visit',
        (string) time(),
        [
          'expires' => time() + (86400 * 30),
          'path' => '/',
          'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
          'httponly' => true,
          'samesite' => 'Lax',
        ]
      );

      header('Location: index.php?route=dashboard');
      exit;
    }
  }
  $error = 'Invalid email or password';
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <title>BrainBow • Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    :root {
      --bg: #0b0e1a;
      --panel: #0e1120;
      --ink: #e5e7eb;
      --muted: #9aa3b2;
      --border: #232842;
      --accent: #7c5cff;
      --accent-2: #5a47ff;
      --danger: #f87171;
      --shadow: 0 10px 30px rgba(0, 0, 0, .35);
    }

    * {
      box-sizing: border-box;
    }

    html,
    body {
      height: 100%;
    }

    body {
      margin: 0;
      font-family: system-ui, -apple-system, Segoe UI, Roboto, Inter, Arial, sans-serif;
      color: var(--ink);
      background:
        radial-gradient(1200px 600px at 10% -10%, rgba(124, 92, 255, .18), transparent 60%),
        radial-gradient(1000px 500px at 110% 110%, rgba(90, 71, 255, .12), transparent 60%),
        var(--bg);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 28px;
    }

    .wrap {
      width: 100%;
      max-width: 980px;
    }

    .brand {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
      margin-bottom: 18px;
      user-select: none;
    }

    .mark {
      width: 42px;
      height: 42px;
      border-radius: 12px;
      background: linear-gradient(135deg, var(--accent), var(--accent-2));
      border: 1px solid rgba(255, 255, 255, .25);
      box-shadow: 0 6px 22px rgba(124, 92, 255, .35);
    }

    .title {
      font-size: 22px;
      letter-spacing: .3px;
    }

    .grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 22px;
    }

    @media (max-width: 840px) {
      .grid {
        grid-template-columns: 1fr;
      }
    }

    .card {
      border: 1px solid var(--border);
      background: linear-gradient(180deg, rgba(20, 23, 40, .82), rgba(8, 10, 22, .82));
      backdrop-filter: blur(6px);
      border-radius: 16px;
      padding: 22px;
      box-shadow: var(--shadow);
      position: relative;
      overflow: hidden;
    }

    .card::before {
      content: "";
      position: absolute;
      inset: -2px;
      background: radial-gradient(600px 200px at 20% -20%, rgba(124, 92, 255, .15), transparent 40%);
      pointer-events: none;
    }

    .card h2 {
      margin: 0 0 4px;
      font-size: 18px;
    }

    .muted {
      color: var(--muted);
      font-size: 13px;
    }

    form {
      margin-top: 12px;
    }

    label {
      display: block;
      font-size: 13px;
      color: var(--muted);
      margin: 8px 0 6px;
    }

    .input {
      width: 100%;
      padding: 12px 12px;
      border-radius: 10px;
      border: 1px solid var(--border);
      background: var(--panel);
      color: var(--ink);
      outline: none;
      transition: border-color .15s, box-shadow .15s;
    }

    .input:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(124, 92, 255, .18);
    }

    .row {
      display: flex;
      gap: 10px;
      align-items: center;
    }

    .btn {
      display: inline-block;
      width: 100%;
      padding: 12px 14px;
      margin-top: 12px;
      border-radius: 10px;
      border: 1px solid var(--border);
      background: linear-gradient(180deg, var(--accent), var(--accent-2));
      color: white;
      font-weight: 600;
      letter-spacing: .2px;
      cursor: pointer;
      text-align: center;
      box-shadow: 0 8px 18px rgba(124, 92, 255, .35);
      transition: transform .06s ease-in-out, filter .15s;
    }

    .btn:hover {
      filter: brightness(1.05);
    }

    .btn:active {
      transform: translateY(1px);
    }

    .err {
      margin-top: 12px;
      padding: 10px 12px;
      border-radius: 10px;
      border: 1px solid rgba(248, 113, 113, .35);
      background: rgba(248, 113, 113, .06);
      color: var(--ink);
    }

    .helper {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      margin-top: 8px;
      color: var(--muted);
      font-size: 13px;
    }

    .a {
      color: #cfd4ff;
      text-decoration: none;
    }

    .a:hover {
      text-decoration: underline;
    }

    .illus {
      border: 1px solid var(--border);
      background: radial-gradient(500px 200px at 100% 100%, rgba(124, 92, 255, .12), transparent 50%), #0c1022;
      border-radius: 16px;
      padding: 20px;
      min-height: 280px;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
    }

    .illus p {
      color: var(--muted);
      line-height: 1.6;
      margin: 0;
    }

    .kbd {
      display: inline-block;
      border: 1px solid var(--border);
      border-bottom: 2px solid #1a1f3a;
      border-radius: 8px;
      padding: 3px 7px;
      background: #0e1329;
      font-size: 12px;
      color: #cfd4ff;
    }

    footer {
      margin-top: 20px;
      text-align: center;
      color: var(--muted);
      font-size: 12px;
    }

    .hr {
      height: 1px;
      background: linear-gradient(90deg, transparent, #1b2140, transparent);
      margin: 18px 0 12px;
    }

    .password-wrap {
      position: relative;
    }

    .toggle {
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      border: 1px solid var(--border);
      background: #11152a;
      color: var(--muted);
      padding: 6px 8px;
      border-radius: 8px;
      font-size: 12px;
      cursor: pointer;
    }

    .toggle:focus {
      outline: none;
      box-shadow: 0 0 0 2px rgba(124, 92, 255, .25);
    }
  </style>
</head>

<body>
  <div class="wrap">
    <div class="brand">
      <div class="mark" aria-hidden="true"></div>
      <div class="title">BrainBow</div>
    </div>

    <div class="grid">
      <!-- Login -->
      <div class="card">
        <h2>Welcome, let's organize your mind out</h2>
        <div class="muted">Sign in to continue to your dashboard.</div>

        <form method="post" autocomplete="on" novalidate>
          <label for="email">Email</label>
          <input id="email" type="email" name="email" class="input" placeholder="you@school.edu" required autofocus />

          <label for="password">Password</label>
          <div class="password-wrap">
            <input id="password" type="password" name="password" class="input" placeholder="••••••••" required />
            <button type="button" class="toggle" aria-label="Show/Hide password" onclick="
                const p=document.getElementById('password');
                p.type = (p.type==='password' ? 'text' : 'password');
                this.textContent = (p.type==='password' ? 'Show' : 'Hide');
              ">Show</button>
          </div>

          <div class="helper">
            <span>
              <a class="a" href="index.php?route=forgot">Forgot password?</a>
              &nbsp;•&nbsp;
              <a class="a" href="index.php?route=register">Create account</a>
            </span>
            <span><span class="kbd">Tab</span> to move • <span class="kbd">Enter</span> to sign in</span>
          </div>

          <button class="btn" type="submit">Login</button>
        </form>

        <?php if (!empty($error)): ?>
          <div class="err"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="hr"></div>
        <!-- <div class="muted" style="font-size:12px">
          By continuing you agree to our <a class="a" href="#">acceptable use</a> and <a class="a" href="#">privacy</a>.
        </div> -->
      </div>

      <div class="illus" aria-hidden="true">
        <p>
          Organize projects, to-dos, notes, mind maps and a calendar<br>all in one place.
        </p>
      </div>
    </div>

    <footer>
      © <?= date('Y') ?> BrainBow — All rights reserved.
    </footer>
  </div>
</body>

</html>
<?php
declare(strict_types=1);

require_once __DIR__ . '/../Lib/db.php';

// Safe session start (your router/middleware may already start it)
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// If already logged in, go to dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: index.php?route=dashboard');
    exit;
}

$error = null;
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = trim($_POST['password'] ?? '');
    $confirm = trim($_POST['confirm'] ?? '');

    if ($name === '' || $email === '' || $password === '' || $confirm === '') {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $db = getDB();

            // Check duplicate email (case-insensitive, no stray spaces)
            $q = $db->prepare('SELECT 1 FROM users WHERE TRIM(LOWER(email)) = TRIM(LOWER(?)) LIMIT 1');
            $q->execute([$email]);

            if ($q->fetchColumn()) {
                $error = 'That email is already registered.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);

                // Your schema has role ENUM('admin','student'); default is 'student'
                // Insert explicitly as 'student' to be crystal-clear.
                $ins = $db->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
                $ins->execute([$name, $email, $hash, 'student']);

                // Auto-login
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $db->lastInsertId();
                $_SESSION['name'] = $name;
                $_SESSION['role'] = 'student';

                header('Location: index.php?route=dashboard');
                exit;
            }
        } catch (Throwable $e) {
            // While debugging you can expose/log it:
            // error_log('[REGISTER] ' . $e->getMessage());
            $error = 'Something went wrong. Please try again.';
        }
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>BrainBow • Create account</title>
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
            --shadow: 0 10px 30px rgba(0, 0, 0, .35);
        }

        * {
            box-sizing: border-box
        }

        html,
        body {
            height: 100%
        }

        body {
            margin: 0;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Inter, Arial, sans-serif;
            color: var(--ink);
            background: radial-gradient(1200px 600px at 10% -10%, rgba(124, 92, 255, .18), transparent 60%),
                radial-gradient(1000px 500px at 110% 110%, rgba(90, 71, 255, .12), transparent 60%), var(--bg);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 28px
        }

        .wrap {
            width: 100%;
            max-width: 980px
        }

        .brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 18px
        }

        .mark {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            border: 1px solid rgba(255, 255, 255, .25);
            box-shadow: 0 6px 22px rgba(124, 92, 255, .35)
        }

        .title {
            font-size: 22px;
            letter-spacing: .3px
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px
        }

        @media (max-width:840px) {
            .grid {
                grid-template-columns: 1fr
            }
        }

        .card {
            border: 1px solid var(--border);
            background: linear-gradient(180deg, rgba(20, 23, 40, .82), rgba(8, 10, 22, .82));
            backdrop-filter: blur(6px);
            border-radius: 16px;
            padding: 22px;
            box-shadow: var(--shadow)
        }

        .card h2 {
            margin: 0 0 4px;
            font-size: 18px
        }

        .muted {
            color: var(--muted);
            font-size: 13px
        }

        label {
            display: block;
            font-size: 13px;
            color: var(--muted);
            margin: 8px 0 6px
        }

        .input {
            width: 100%;
            padding: 12px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: var(--panel);
            color: var(--ink);
            outline: none
        }

        .input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(124, 92, 255, .18)
        }

        .btn {
            display: inline-block;
            width: 100%;
            padding: 12px 14px;
            margin-top: 12px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: linear-gradient(180deg, var(--accent), var(--accent-2));
            color: #fff;
            font-weight: 600;
            letter-spacing: .2px;
            cursor: pointer;
            text-align: center;
            box-shadow: 0 8px 18px rgba(124, 92, 255, .35)
        }

        .a {
            color: #cfd4ff;
            text-decoration: none
        }

        .a:hover {
            text-decoration: underline
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
            text-align: center
        }

        .alert {
            margin-top: 12px;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid rgba(248, 113, 113, .35);
            background: rgba(248, 113, 113, .06)
        }

        footer {
            margin-top: 20px;
            text-align: center;
            color: var(--muted);
            font-size: 12px
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
            <div class="card">
                <h2>Create account</h2>
                <div class="muted">Join BrainBow to start organizing your work.</div>

                <form method="post" autocomplete="on" novalidate>
                    <label for="name">Name</label>
                    <input id="name" type="text" name="name" class="input" placeholder="Your name" required
                        value="<?= htmlspecialchars($name) ?>" />
                    <label for="email">Email</label>
                    <input id="email" type="email" name="email" class="input" placeholder="you@school.edu" required
                        value="<?= htmlspecialchars($email) ?>" />
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" class="input" placeholder="••••••••"
                        required />
                    <label for="confirm">Confirm password</label>
                    <input id="confirm" type="password" name="confirm" class="input" placeholder="••••••••" required />

                    <div class="muted" style="margin-top:8px">
                        Already have an account? <a class="a" href="index.php?route=login">Sign in</a>
                    </div>

                    <button class="btn" type="submit">Create account</button>
                </form>

                <?php if (!empty($error)): ?>
                    <div class="alert"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
            </div>

            <div class="illus" aria-hidden="true">
                <p>Everything you need to plan school and life, in one place.</p>
            </div>
        </div>
        <footer>© <?= date('Y') ?> BrainBow — All rights reserved.</footer>
    </div>
</body>

</html>
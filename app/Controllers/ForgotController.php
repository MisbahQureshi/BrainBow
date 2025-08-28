<?php
declare(strict_types=1);

require_once __DIR__ . '/../Lib/db.php';

// Safe session start
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// We allow reset even if logged in, but you could redirect to a change-password page.

$error = null;
$success = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = trim($_POST['password'] ?? '');
    $confirm = trim($_POST['confirm'] ?? '');

    if ($email === '' || $password === '' || $confirm === '') {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email.';
    } elseif (strlen($password) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $db = getDB();

            // Find account by email (case-insensitive)
            $q = $db->prepare('SELECT id FROM users WHERE TRIM(LOWER(email)) = TRIM(LOWER(?)) LIMIT 1');
            $q->execute([$email]);
            $user = $q->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $error = 'No account found for that email.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $upd = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $upd->execute([$hash, (int) $user['id']]);
                $success = 'Password updated. You can now sign in.';
            }
        } catch (Throwable $e) {
            // error_log('[FORGOT] ' . $e->getMessage());
            $error = 'Something went wrong. Please try again.';
        }
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>BrainBow • Reset password</title>
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
            border-radius: 10px
        }

        .alert.err {
            border: 1px solid rgba(248, 113, 113, .35);
            background: rgba(248, 113, 113, .06)
        }

        .alert.ok {
            border: 1px solid rgba(52, 211, 153, .35);
            background: rgba(52, 211, 153, .08)
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
                <h2>Reset password</h2>
                <div class="muted">Enter your account email and choose a new password.</div>

                <form method="post" autocomplete="on" novalidate>
                    <label for="email">Email</label>
                    <input id="email" type="email" name="email" class="input" placeholder="you@school.edu" required
                        value="<?= htmlspecialchars($email) ?>" />
                    <label for="password">New password</label>
                    <input id="password" type="password" name="password" class="input" placeholder="••••••••"
                        required />
                    <label for="confirm">Confirm new password</label>
                    <input id="confirm" type="password" name="confirm" class="input" placeholder="••••••••" required />

                    <div class="muted" style="margin-top:8px"><a class="a" href="index.php?route=login">Back to sign
                            in</a></div>
                    <button class="btn" type="submit">Update password</button>
                </form>

                <?php if (!empty($error)): ?>
                    <div class="alert err"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert ok"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
            </div>

            <div class="illus" aria-hidden="true">
                <p>Tip: Use a strong password you don’t reuse elsewhere.</p>
            </div>
        </div>
        <footer>© <?= date('Y') ?> BrainBow — All rights reserved.</footer>
    </div>
</body>

</html>
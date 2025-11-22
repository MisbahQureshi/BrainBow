<?php
// Expect: $title (string), $content (string), $projects (array)
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8" />
    <title><?= htmlspecialchars($title ?? 'BrainBow') ?></title>
    <style>
        :root {
            --bg: #0f1220;
            --card: #171a2b;
            --muted: #9aa0b4;
            --pri: #7c5cff;
            --ok: #22c55e;
            --warn: #f59e0b;
        }

        * {
            box-sizing: border-box
        }

        body {
            margin: 0;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial;
            background: var(--bg);
            color: #e5e7eb;
        }

        a {
            color: #c7c9ff;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline
        }

        .app {
            display: grid;
            grid-template-columns: 260px 1fr;
            min-height: 100vh;
        }

        .sidebar {
            background: #0b0e1a;
            border-right: 1px solid #1f2437;
            padding: 18px 14px;
        }

        .logo {
            font-weight: 900;
            letter-spacing: .5px;
            color: #fff;
            margin-bottom: 14px
        }

        .section {
            margin: 16px 0 6px;
            font-size: 12px;
            color: var(--muted);
            text-transform: uppercase;
        }

        .nav a {
            display: block;
            padding: 8px 10px;
            border-radius: 10px;
            margin: 4px 0;
            background: transparent;
        }

        .nav a.active,
        .nav a:hover {
            background: #14172a;
        }

        .projects .pill {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            border-radius: 10px;
            margin: 4px 0;
            background: #0e1120;
        }

        .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
        }

        .main {
            padding: 22px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 18px;
        }

        .card {
            grid-column: span 4;
            background: var(--card);
            padding: 16px;
            border-radius: 16px;
            border: 1px solid #21253a;
        }

        .card h3 {
            margin: 0 0 10px;
            font-size: 16px;
        }

        .list {
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .list li {
            padding: 8px 0;
            border-bottom: 1px solid #1e2236;
        }

        .muted {
            color: var(--muted)
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px
        }

        .btn {
            display: inline-block;
            background: var(--pri);
            color: #fff;
            padding: 8px 12px;
            border-radius: 10px;
        }

        .see-more {
            float: right;
            font-size: 12px;
            color: #c0c4d8
        }

        @media (max-width:1000px) {
            .card {
                grid-column: span 12;
            }

            .app {
                grid-template-columns: 1fr;
            }

            .sidebar {
                position: sticky;
                top: 0;
            }
        }
    </style>
</head>

<body>
    <div class="app">
        <aside class="sidebar">
            <div class="logo">BrainBow</div>

            <div class="section">Tools</div>
            <nav class="nav">
                <a href="index.php?route=dashboard"
                    class="<?= ($_GET['route'] ?? '') === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
                <a href="index.php?route=todos.list">To-dos</a>
                <a href="index.php?route=notes.list">Notes</a>
                <a href="index.php?route=mindmaps.list">Mind maps</a>
                <a href="index.php?route=whiteboards.list">Whiteboards</a>
                <a href="index.php?route=events.calendar">Calendar</a>
            </nav>

            <div class="section">Projects</div>
            <div class="projects">
                <?php foreach ($projects as $p): ?>
                    <a class="pill" href="index.php?route=projects.view&id=<?= (int) $p['id'] ?>">
                        <span class="dot" style="background: <?= htmlspecialchars($p['color']) ?>"></span>
                        <span><?= htmlspecialchars($p['name']) ?></span>
                    </a>
                <?php endforeach; ?>
                <p style="margin-top:8px"><a class="btn" href="index.php?route=projects.new">+ New Project</a></p>
            </div>

            <div class="section">Account</div>
            <div class="nav">
                <a href="index.php?route=logout">Logout</a>
            </div>
        </aside>

        <main class="main">
            <?= $content ?>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>
        $(function () {
            $(".btn").hover(
                function () { $(this).css("background-color", "#583acf"); },
                function () { $(this).css("background-color", ""); }
            );

            $(".btn").on("mousedown", function () {
                $(this).css("transform", "scale(0.98)");
            }).on("mouseup mouseleave", function () {
                $(this).css("transform", "");
            });
        });
    </script>

</body>

</html>
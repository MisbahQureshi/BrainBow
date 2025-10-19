<?php
declare(strict_types=1);

session_start();

require __DIR__ . '/../vendor/autoload.php';

// (Dev only) show errors while building
ini_set('display_errors', '1');
error_reporting(E_ALL);

// Load .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$route = $_GET['route'] ?? 'login';

switch ($route) {
    case 'health':
        require __DIR__ . '/../app/Lib/db.php';
        $pdo = getDB();
        $row = $pdo->query('SELECT DATABASE() db, @@port port, NOW() now')->fetch();
        echo "<pre>DB: {$row['db']} | Port: {$row['port']} | Time: {$row['now']}</pre>";
        echo '<a href="index.php?route=login">Go to login</a>';
        break;

    case 'login':
        require __DIR__ . '/../app/Controllers/AuthController.php';
        break;

    case 'register':
        require __DIR__ . '/../app/Controllers/RegisterController.php';
        break;
        
    case 'forgot':
        require __DIR__ . '/../app/Controllers/ForgotController.php';
        break;

    case 'dashboard':
        require __DIR__ . '/../app/Controllers/DashboardController.php';
        break;

    case 'logout':
        session_unset();
        session_destroy();
        header('Location: index.php?route=login');
        break;

    case 'todos.list':
    case 'todos.new':
    case 'todos.view':
    case 'todos.toggle':
    case 'todos.delete':
        require __DIR__ . '/../app/Controllers/TodosController.php';
        break;

    case 'notes.list':
    case 'notes.new':
    case 'notes.view':
    case 'notes.edit':
    case 'notes.delete':
    case 'notes.pin':
    case 'notes.search':
        require __DIR__ . '/../app/Controllers/NotesController.php';
        break;

    case 'whiteboards.list':
    case 'whiteboards.new':
    case 'whiteboards.view':
    case 'whiteboards.edit':
    case 'whiteboards.delete':
        require __DIR__ . '/../app/Controllers/WhiteboardsController.php';
        break;

    case 'mindmaps.list':
    case 'mindmaps.new':
    case 'mindmaps.view':
    case 'mindmaps.edit':
    case 'mindmaps.delete':
        require __DIR__ . '/../app/Controllers/MindmapsController.php';
        break;

    case 'events.list':
    case 'events.new':
    case 'events.view':
    case 'events.edit':
    case 'events.delete':
    case 'events.calendar':
        require __DIR__ . '/../app/Controllers/EventsController.php';
        break;

    case 'projects.new':
    case 'projects.view':
        require __DIR__ . '/../app/Controllers/ProjectsController.php';
        break;

    case 'user.delete':
        require __DIR__ . '/../app/Controllers/AccountController.php';
        break;


    default:
        http_response_code(404);
        echo '404';
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/../Lib/db.php';
require_once __DIR__ . '/../Middleware/Auth.php';

session_start();

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?route=login');
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

$pdo = getDB();
$userId = (int) $_SESSION['user_id'];

if (($_SESSION['role'] ?? '') === 'admin') {
    echo 'Admins cannot delete their own account.';
    exit;
}

try {
    // Delete the user
    $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$userId]);

    // If delete was successful
    if ($stmt->rowCount() > 0) {
        session_unset();
        session_destroy();

        // Redirect with confirmation
        header('Location: index.php?route=register&deleted=1');
        exit;
    } else {
        echo 'No account deleted (user not found).';
    }
} catch (PDOException $e) {
    error_log('User delete failed: ' . $e->getMessage());
    echo 'Error deleting your account.';
}

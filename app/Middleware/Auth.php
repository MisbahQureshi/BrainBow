<?php
declare(strict_types=1);

function require_login(): void
{
  if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?route=login');
    exit;
  }
}

function csrf_token(): string
{
  if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
  }
  return $_SESSION['csrf'];
}
function verify_csrf(): void
{
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ok = isset($_POST['csrf']) && hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf']);
    if (!$ok) {
      http_response_code(403);
      exit('Invalid CSRF token');
    }
  }
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function auth_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        if (session_name() === 'hruform_admin_sid') {
            return;
        }
        // A different named session (e.g. the site session) is active in this
        // request; PHP only supports one active session at a time, so close it
        // before switching to the admin session.
        session_write_close();
    }
    session_name('hruform_admin_sid');
    session_start();
}

function auth_attempt(string $username, string $password): bool
{
    $stmt = db()->prepare('SELECT id, username, password_hash FROM admin_users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    auth_start_session();
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $user['id'];
    $_SESSION['admin_username'] = $user['username'];

    return true;
}

function auth_check(): bool
{
    auth_start_session();
    return isset($_SESSION['admin_id']);
}

function auth_require_login(): void
{
    if (!auth_check()) {
        header('Location: login.php');
        exit;
    }
}

function auth_logout(): void
{
    auth_start_session();
    $_SESSION = [];
    session_destroy();
}

function auth_current_username(): ?string
{
    auth_start_session();
    return $_SESSION['admin_username'] ?? null;
}

function auth_current_id(): ?int
{
    auth_start_session();
    return isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
}

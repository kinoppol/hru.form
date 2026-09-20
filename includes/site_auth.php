<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function site_start_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('hruform_sid');
        session_start();
    }
}

function site_register(string $fullName, string $email, string $password): array
{
    $stmt = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return [false, 'อีเมลนี้ถูกใช้งานแล้ว'];
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = db()->prepare('INSERT INTO users (full_name, email, password_hash) VALUES (?, ?, ?)');
    $stmt->execute([$fullName, $email, $hash]);

    site_login_id((int) db()->lastInsertId(), $fullName);
    return [true, null];
}

function site_attempt(string $email, string $password): bool
{
    $stmt = db()->prepare('SELECT id, full_name, password_hash FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    site_login_id((int) $user['id'], $user['full_name']);
    return true;
}

function site_login_id(int $id, string $fullName): void
{
    site_start_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $id;
    $_SESSION['user_name'] = $fullName;
}

function site_check(): bool
{
    site_start_session();
    return isset($_SESSION['user_id']);
}

function site_require_login(): void
{
    if (!site_check()) {
        header('Location: login.php');
        exit;
    }
}

function site_user_id(): ?int
{
    site_start_session();
    return $_SESSION['user_id'] ?? null;
}

function site_user_name(): ?string
{
    site_start_session();
    return $_SESSION['user_name'] ?? null;
}

function site_logout(): void
{
    site_start_session();
    $_SESSION = [];
    session_destroy();
}

<?php
// app/auth.php — Gestion de l'authentification et des utilisateurs
require_once __DIR__ . '/../config/database.php';

function ensureSessionStarted(): void {
    if (session_status() === PHP_SESSION_NONE) {
        $cookieParams = session_get_cookie_params();
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $cookieParams['path'] ?: '/',
            'domain' => $cookieParams['domain'] ?: '',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

ensureSessionStarted();

function hasUsers(): bool {
    $db = getDB();
    $stmt = $db->query('SELECT 1 FROM users LIMIT 1');
    return (bool) $stmt->fetchColumn();
}

function createUser(string $username, string $password, string $role = 'user'): bool {
    $db = getDB();
    $username = trim($username);
    if ($username === '') {
        return false;
    }
    $role = in_array($role, ['user', 'admin'], true) ? $role : 'user';

    if (!hasUsers()) {
        // Le premier compte créé devient administrateur.
        $role = 'admin';
    }

    $stmt = $db->prepare('SELECT 1 FROM users WHERE username = :username');
    $stmt->execute([':username' => $username]);
    if ($stmt->fetch()) {
        return false;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $insert = $db->prepare('INSERT INTO users (username, password_hash, password_plain, role, created_at) VALUES (:username, :password_hash, :password_plain, :role, CURRENT_TIMESTAMP)');
    return $insert->execute([
        ':username' => $username,
        ':password_hash' => $hash,
        ':password_plain' => $password,
        ':role' => $role,
    ]);
}

function login(string $username, string $password): bool {
    $db = getDB();
    $stmt = $db->prepare('SELECT username, password_hash, role FROM users WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => trim($username)]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        return false;
    }

    if (!password_verify($password, $user['password_hash'])) {
        return false;
    }

    ensureSessionStarted();
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    return true;
}

function getLoginUrl(): string {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    return strpos($scriptName, '/app/') !== false ? 'login.php' : 'app/login.php';
}

function require_login(): void {
    ensureSessionStarted();
    if (!isset($_SESSION['username'])) {
        header('Location: ' . getLoginUrl());
        exit;
    }
}

function is_logged_in(): bool {
    ensureSessionStarted();
    return isset($_SESSION['username']);
}

function check_admin(): void {
    ensureSessionStarted();
    if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'admin') {
        header('Location: ' . getLoginUrl());
        exit;
    }
}

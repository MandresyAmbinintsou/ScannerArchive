<?php
// app/auth.php — Gestion de l'authentification et des utilisateurs
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
    $insert = $db->prepare('INSERT INTO users (username, password_hash, role, created_at) VALUES (:username, :password_hash, :role, CURRENT_TIMESTAMP)');
    return $insert->execute([
        ':username' => $username,
        ':password_hash' => $hash,
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

    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    return true;
}

function check_admin(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'admin') {
        header('Location: login.php');
        exit;
    }
}

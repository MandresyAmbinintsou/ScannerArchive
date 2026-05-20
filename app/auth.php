<?php
// app/auth.php — Gestion de l'authentification et des utilisateurs
require_once __DIR__ . '/../config/database.php';

const ACCESS_LEVEL_SUPERADMIN = 0;
const ACCESS_LEVEL_ADMIN = 1;
const ACCESS_LEVEL_DIRECTORY = 2;

function ensureSessionStarted(): void {
    if (session_status() === PHP_SESSION_NONE) {
        $cookieParams = session_get_cookie_params();
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        // Signature compatible PHP 5.x, 7.x et 8.x
        if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => $cookieParams['path'] ?: '/',
                'domain' => $cookieParams['domain'] ?: '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            session_set_cookie_params(
                0,
                ($cookieParams['path'] ?: '/') . '; samesite=Lax',
                $cookieParams['domain'] ?: '',
                $secure,
                true
            );
        }
        session_start();
    }
}

ensureSessionStarted();

function normalizeAccessParams(string $roleOrLevel): array {
    $value = trim((string)$roleOrLevel);
    if ($value === '') {
        $value = 'user';
    }

    // Accepte soit un rôle historique ('admin'/'user'), soit un niveau ('0'/'1'/'2').
    if (in_array($value, ['0', '1', '2'], true)) {
        $level = (int)$value;
        if ($level <= ACCESS_LEVEL_ADMIN) {
            return ['role' => 'admin', 'access_level' => $level];
        }
        return ['role' => 'user', 'access_level' => ACCESS_LEVEL_DIRECTORY];
    }

    // Mode historique
    $role = in_array($value, ['user', 'admin'], true) ? $value : 'user';
    $level = ($role === 'admin') ? ACCESS_LEVEL_SUPERADMIN : ACCESS_LEVEL_DIRECTORY;
    return ['role' => $role, 'access_level' => $level];
}

function sessionAccessLevel(): int {
    ensureSessionStarted();
    if (isset($_SESSION['access_level'])) {
        return (int)$_SESSION['access_level'];
    }
    // Compatibilité : anciens systèmes sans access_level en session
    return (($_SESSION['role'] ?? '') === 'admin') ? ACCESS_LEVEL_SUPERADMIN : ACCESS_LEVEL_DIRECTORY;
}

function hasUsers(): bool {
    $db = getDB();
    $stmt = $db->query('SELECT 1 FROM users LIMIT 1');
    return (bool) $stmt->fetchColumn();
}

function createUser(string $username, string $password, string $role = 'user', ?bool $approved = null): bool {
    $db = getDB();
    $username = trim($username);
    if ($username === '') {
        return false;
    }
    $normalized = normalizeAccessParams($role);
    $role = $normalized['role'];
    $accessLevel = (int)$normalized['access_level'];

    if (!hasUsers()) {
        // Le premier compte créé devient administrateur.
        $role = 'admin';
        $accessLevel = ACCESS_LEVEL_SUPERADMIN;
    }

    // Par défaut (compatibilité), on crée des comptes approuvés.
    // Le formulaire public peut passer $approved=false pour exiger l'approbation admin.
    if ($approved === null) {
        $approved = true;
    }

    $stmt = $db->prepare('SELECT 1 FROM users WHERE username = :username');
    $stmt->execute([':username' => $username]);
    if ($stmt->fetch()) {
        return false;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $insert = $db->prepare(
        'INSERT INTO users (username, password_hash, password_plain, role, access_level, is_approved, approved_at, created_at)
         VALUES (:username, :password_hash, :password_plain, :role, :access_level, :is_approved, :approved_at, CURRENT_TIMESTAMP)'
    );
    return $insert->execute([
        ':username' => $username,
        ':password_hash' => $hash,
        ':password_plain' => $password,
        ':role' => $role,
        ':access_level' => $accessLevel,
        ':is_approved' => $approved ? 1 : 0,
        ':approved_at' => $approved ? date('c') : null,
    ]);
}

function login(string $username, string $password): bool {
    $db = getDB();
    $stmt = $db->prepare('SELECT username, password_hash, role, access_level, is_approved FROM users WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => trim($username)]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        return false;
    }

    if (!(bool)($user['is_approved'] ?? true)) {
        ensureSessionStarted();
        $_SESSION['login_error'] = "Compte en attente d'approbation administrateur.";
        return false;
    }

    if (!password_verify($password, $user['password_hash'])) {
        return false;
    }

    ensureSessionStarted();
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['access_level'] = isset($user['access_level']) ? (int)$user['access_level'] : (($user['role'] ?? '') === 'admin' ? ACCESS_LEVEL_SUPERADMIN : ACCESS_LEVEL_DIRECTORY);
    unset($_SESSION['login_error']);
    return true;
}

function getLoginUrl(): string {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    return strpos($scriptName, '/app/') !== false ? 'login.php' : 'app/login.php';
}

function getBaseUrl(): string {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    if (strpos($scriptName, '/app/') !== false) {
        return '../';
    }
    return '';
}

function require_login(): void {
    ensureSessionStarted();
    if (!isset($_SESSION['username'])) {
        header('Location: ' . getLoginUrl());
        exit;
    }

    // Niveau 2 : ne doit accéder qu'au répertoire (pages publiques de lecture).
    if (sessionAccessLevel() >= ACCESS_LEVEL_DIRECTORY) {
        $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $allowed = [
            'index.php',
            'folder.php',
            'print_pdf.php',
            'matricules.php',
            'sousdossiers.php',
            'images.php',
            'image.php',
            'refresh.php',
            'logout.php',
            'user_profil.php'
        ];
        if ($script !== '' && !in_array($script, $allowed, true)) {
            header('Location: ' . getBaseUrl() . 'index.php');
            exit;
        }
    }
}

function is_logged_in(): bool {
    ensureSessionStarted();
    return isset($_SESSION['username']);
}

function check_admin(): void {
    ensureSessionStarted();
    if (!isset($_SESSION['username'])) {
        header('Location: ' . getLoginUrl());
        exit;
    }
    $level = sessionAccessLevel();
    if ($level > ACCESS_LEVEL_ADMIN) {
        header('Location: ' . getBaseUrl() . 'index.php');
        exit;
    }
}

function check_superadmin(): void {
    ensureSessionStarted();
    if (!isset($_SESSION['username'])) {
        header('Location: ' . getLoginUrl());
        exit;
    }
    $level = sessionAccessLevel();
    if ($level !== ACCESS_LEVEL_SUPERADMIN) {
        header('Location: ' . getBaseUrl() . 'index.php');
        exit;
    }
}

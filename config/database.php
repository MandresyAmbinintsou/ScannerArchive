<?php
// ============================================================
// config/database.php — Connexion PostgreSQL
// ============================================================

define('DB_HOST', getenv('DB_HOST') ?: getenv('PGHOST') ?: '');
define('DB_PORT', getenv('DB_PORT') ?: getenv('PGPORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: 'archive_db');
define('DB_USER', getenv('DB_USER') ?: getenv('PGUSER') ?: 'postgres');

$pgpass = getenv('DB_PASS') ?: getenv('PGPASSWORD');
define('DB_PASS', $pgpass !== false ? $pgpass : '');

$defaultArchiveRoot = getenv('ARCHIVE_ROOT');
if (!$defaultArchiveRoot) {
    if (PHP_OS_FAMILY === 'Windows') {
        $defaultArchiveRoot = getenv('USERPROFILE') ? getenv('USERPROFILE') . '\\archive' : 'C:\\archive';
    } else {
        $defaultArchiveRoot = getenv('HOME') ? getenv('HOME') . '/archive' : '/data/archive';
    }
}

define('ARCHIVE_ROOT', $defaultArchiveRoot);  // Peut être remplacé par la variable d'environnement ARCHIVE_ROOT

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        if (DB_HOST === '') {
            $dsn = sprintf('pgsql:dbname=%s', DB_NAME);
        } elseif (DB_HOST[0] === '/') {
            // Utilisation d'un socket UNIX personnalisé
            $dsn = sprintf('pgsql:host=%s;dbname=%s', DB_HOST, DB_NAME);
        } else {
            $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', DB_HOST, DB_PORT, DB_NAME);
        }

        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_PERSISTENT         => true,  // connexion persistante = plus rapide
        ]);
    }
    return $pdo;
}

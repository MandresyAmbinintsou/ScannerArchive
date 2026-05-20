<?php
// ============================================================
// config/database.php — Connexion PostgreSQL avec Auto-Setup
// ============================================================

define('DB_HOST', getenv('DB_HOST') ?: getenv('PGHOST') ?: '');
define('DB_PORT', getenv('DB_PORT') ?: getenv('PGPORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: 'archive_db');

$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

$defaultDbUser = getenv('DB_USER') ?: getenv('PGUSER');
if ($defaultDbUser === false || $defaultDbUser === '') {
    // Under Windows, the environment username often does not match a PostgreSQL role.
    if ($isWindows) {
        $defaultDbUser = 'postgres';
    } else {
        $defaultDbUser = getenv('USER') ?: getenv('LOGNAME') ?: get_current_user();
    }
}
define('DB_USER', $defaultDbUser ?: 'postgres');

$pgpass = getenv('DB_PASS') ?: getenv('PGPASSWORD');
define('DB_PASS', $pgpass !== false ? $pgpass : '');

$defaultArchiveRoot = getenv('ARCHIVE_ROOT');
if (!$defaultArchiveRoot) {
    if ($isWindows) {
        $defaultArchiveRoot = getenv('USERPROFILE') ? getenv('USERPROFILE') . '\\archive' : 'C:\\archive';
    } else {
        $defaultArchiveRoot = getenv('HOME') ? getenv('HOME') . '/archive' : '/data/archive';
    }
}
define('ARCHIVE_ROOT', $defaultArchiveRoot);

/**
 * Retourne une instance PDO, crée la base et les tables si nécessaire.
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsnApp = buildDsn(DB_NAME);
        $dsnSystem = buildDsn('postgres');

        try {
            // 1. Tenter de se connecter à la base de l'application
            $pdo = new PDO($dsnApp, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_PERSISTENT         => true,
            ]);
        } catch (PDOException $e) {
            // Detect missing database across translated PG messages and SQLSTATE codes.
            $pdoSqlState = $e->errorInfo[0] ?? $e->getCode();
            $message = $e->getMessage();
            $isMissingDb = $pdoSqlState === '3D000'
                || $pdoSqlState === '08006'
                || stripos($message, 'does not exist') !== false
                || stripos($message, 'existe pas') !== false;

            if ($isMissingDb) {
                try {
                    // 2. Se connecter à la base système 'postgres' pour créer 'archive_db'
                    $tempPdo = new PDO($dsnSystem, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                    $tempPdo->exec("CREATE DATABASE " . DB_NAME);
                    $tempPdo = null;

                    // 3. Se reconnecter à la base nouvellement créée
                    $pdo = new PDO($dsnApp, DB_USER, DB_PASS, [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]);
                } catch (PDOException $e2) {
                    throw new RuntimeException("Erreur lors de la création automatique de la base : " . $e2->getMessage());
                }
            } else {
                throw $e;
            }
        }

        // 4. Vérifier et créer le schéma si les tables sont absentes
        ensureSchema($pdo);
    }
    return $pdo;
}

/**
 * Construit le DSN PostgreSQL selon le host
 */
function buildDsn(string $dbname): string {
    if (DB_HOST === '') {
        return sprintf('pgsql:dbname=%s', $dbname);
    } elseif (DB_HOST[0] === '/') {
        return sprintf('pgsql:host=%s;dbname=%s', DB_HOST, $dbname);
    } else {
        return sprintf('pgsql:host=%s;port=%s;dbname=%s', DB_HOST, DB_PORT, $dbname);
    }
}

/**
 * Crée les tables à partir du fichier SQL si la table 'matricules' n'existe pas.
 */
function ensureSchema(PDO $pdo): void {
    $stmt = $pdo->query("SELECT 1 FROM pg_catalog.pg_tables WHERE schemaname = 'public' AND tablename = 'matricules'");
    if (!$stmt->fetch()) {
        $schemaFile = __DIR__ . '/../scripts/schema.pg.sql';
        if (file_exists($schemaFile)) {
            $sql = file_get_contents($schemaFile);
            $pdo->exec($sql);
        }
    }

    $stmt2 = $pdo->query("SELECT 1 FROM pg_catalog.pg_tables WHERE schemaname = 'public' AND tablename = 'users'");
    if (!$stmt2->fetch()) {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS users (
                id BIGSERIAL PRIMARY KEY,
                username VARCHAR(150) NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                password_plain TEXT NULL,
                role VARCHAR(20) NOT NULL DEFAULT 'user',
                is_approved BOOLEAN NOT NULL DEFAULT TRUE,
                approved_at TIMESTAMPTZ NULL,
                created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
            )"
        );
    }

    // Migrations légères (ajout de colonnes si absent).
    // 1) is_approved
    $stmtCol = $pdo->prepare("
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = 'users' AND column_name = :col
        LIMIT 1
    ");
    $stmtCol->execute([':col' => 'is_approved']);
    if (!$stmtCol->fetchColumn()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_approved BOOLEAN NOT NULL DEFAULT TRUE");
        // Les comptes existants restent approuvés.
        $pdo->exec("UPDATE users SET is_approved = TRUE WHERE is_approved IS NULL");
    }

    // 2) approved_at
    $stmtCol->execute([':col' => 'approved_at']);
    if (!$stmtCol->fetchColumn()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN approved_at TIMESTAMPTZ NULL");
    }

    // 3) access_level (0=superadmin, 1=admin, 2=répertoire)
    $stmtCol->execute([':col' => 'access_level']);
    if (!$stmtCol->fetchColumn()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN access_level SMALLINT NULL");
        // Compatibilité : les anciens admins restent "total" (0), les users restent 2.
        $pdo->exec("UPDATE users SET access_level = 0 WHERE (role = 'admin') AND (access_level IS NULL)");
        $pdo->exec("UPDATE users SET access_level = 2 WHERE (role <> 'admin' OR role IS NULL) AND (access_level IS NULL)");
        $pdo->exec("ALTER TABLE users ALTER COLUMN access_level SET DEFAULT 2");
        $pdo->exec("ALTER TABLE users ALTER COLUMN access_level SET NOT NULL");
    }
}

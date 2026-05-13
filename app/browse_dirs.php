<?php
// ============================================================
// browse_dirs.php — Explorateur de dossiers (serveur)
// JSON API pour sélectionner un chemin sur la machine serveur.
// ============================================================

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

check_admin();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function isWindowsOs(): bool {
    return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
}

function normalizeDirPath(string $path): string {
    $path = trim($path);
    if ($path === '') {
        return '';
    }
    if (isWindowsOs()) {
        // Normaliser les racines de lecteurs (ex: "C:" -> "C:\")
        if (preg_match('/^[a-zA-Z]:$/', $path) === 1) {
            $path .= '\\';
        }
    }
    return $path;
}

function listRoots(): array {
    $roots = [];
    $archive = ARCHIVE_ROOT;
    if ($archive !== '') {
        $roots[] = ['name' => 'ARCHIVE_ROOT', 'path' => $archive];
    }

    if (isWindowsOs()) {
        foreach (range('A', 'Z') as $letter) {
            $drive = $letter . ':\\';
            if (@is_dir($drive)) {
                $roots[] = ['name' => $drive, 'path' => $drive];
            }
        }
        return $roots;
    }

    $roots[] = ['name' => '/', 'path' => '/'];
    return $roots;
}

try {
    $action = trim((string)($_GET['action'] ?? ''));
    $path = normalizeDirPath((string)($_GET['path'] ?? ''));

    if ($action === 'roots' || $path === '') {
        echo json_encode([
            'ok' => true,
            'mode' => 'roots',
            'roots' => listRoots(),
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $real = realpath($path);
    if ($real === false || !is_dir($real)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Chemin invalide : ' . $path], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $dirs = [];
    try {
        $it = new FilesystemIterator($real, FilesystemIterator::SKIP_DOTS);
        foreach ($it as $item) {
            if (!$item->isDir() || $item->isLink()) {
                continue;
            }
            $dirs[] = [
                'name' => $item->getFilename(),
                'path' => $item->getPathname(),
            ];
        }
    } catch (UnexpectedValueException $e) {
        // Permission refusée
        $dirs = [];
    }

    usort($dirs, static fn($a, $b) => strcasecmp($a['name'], $b['name']));

    $parent = dirname($real);
    if (isWindowsOs()) {
        // Sur Windows, dirname("C:\") retourne "C:\" mais on évite boucle infinie.
        if (preg_match('/^[a-zA-Z]:\\\\$/', $real) === 1) {
            $parent = null;
        }
    } else {
        if ($real === '/') {
            $parent = null;
        }
    }

    echo json_encode([
        'ok' => true,
        'mode' => 'list',
        'current' => $real,
        'parent' => $parent,
        'dirs' => $dirs,
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_SLASHES);
}


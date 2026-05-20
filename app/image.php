<?php
// ============================================================
// app/image.php — Sert une image depuis le disque local
// ============================================================

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

ensureSessionStarted();

try {
    // Éviter de polluer un flux binaire (PDF/IMG) avec des warnings/notices.
    // Les erreurs sont converties en HTTP 500 propre.
    ini_set('display_errors', '0');
    error_reporting(E_ALL);

    $imageId = (int)($_GET['id'] ?? 0);
    if ($imageId <= 0) {
        http_response_code(400);
        exit('ID invalide');
    }

    $db   = getDB();
    $stmt = $db->prepare('SELECT chemin_complet FROM images WHERE id = :id');
    $stmt->execute([':id' => $imageId]);
    $row  = $stmt->fetch();

    if (!$row) {
        http_response_code(404);
        exit('Image non trouvée');
    }

    $path = $row['chemin_complet'];
} catch (Throwable $e) {
    http_response_code(500);
    exit($e->getMessage());
}

// Sécurité : vérifier que le chemin est autorisé
// On essaie de déterminer le root autorisé le plus pertinent
$allowedRoot = $_SESSION['archive_root'] ?? '';

// Si pas en session (ex: utilisateur lecture seule), on récupère le dernier root indexé
if ($allowedRoot === '') {
    try {
        require_once __DIR__ . '/scan.php'; // Pour getLastScannedRoot
        $db = getDB();
        $allowedRoot = getLastScannedRoot($db) ?: ARCHIVE_ROOT;
        $_SESSION['archive_root'] = $allowedRoot;
    } catch (Throwable $t) {
        $allowedRoot = ARCHIVE_ROOT;
    }
}

$realPath = realpath($path);
$realAllowed = realpath($allowedRoot);

// Sur Windows, on utilise stripos car les chemins sont insensibles à la casse
if ($realPath === false || $realAllowed === false || stripos($realPath, $realAllowed) !== 0) {
    // Si l'échec est dû à un changement de root non répercuté en session, on tente une dernière vérification via DB
    try {
        require_once __DIR__ . '/scan.php';
        $db = getDB();
        $dbRoot = getLastScannedRoot($db);
        if ($dbRoot) {
            $realAllowed = realpath($dbRoot);
            if ($realAllowed && stripos($realPath, $realAllowed) === 0) {
                $_SESSION['archive_root'] = $dbRoot;
                goto proceed;
            }
        }
    } catch (Throwable $t) {}

    http_response_code(403);
    exit('Accès refusé ou fichier hors limite');
}

proceed:
if (!file_exists($path)) {
    http_response_code(404);
    exit('Fichier introuvable');
}

// Déterminer le type MIME
$ext      = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mimeMap  = [
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'pdf'  => 'application/pdf',
];
$mime = $mimeMap[$ext] ?? 'application/octet-stream';

// Désactiver le cache pour éviter de voir d'anciennes images après une ré-indexation
header('Content-Type: ' . $mime);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Content-Length: ' . filesize($path));

readfile($path);
exit;

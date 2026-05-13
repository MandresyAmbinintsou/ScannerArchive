<?php
// ============================================================
// app/image.php — Sert une image depuis le disque local
// ============================================================

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

ensureSessionStarted();

try {
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
// On utilise le root en session s'il existe, sinon la constante
$allowedRoot = $_SESSION['archive_root'] ?? ARCHIVE_ROOT;
$realPath = realpath($path);
$realAllowed = realpath($allowedRoot);

// Sur Windows, on utilise stripos car les chemins sont insensibles à la casse
if ($realPath === false || $realAllowed === false || stripos($realPath, $realAllowed) !== 0) {
    http_response_code(403);
    exit('Accès refusé ou fichier hors limite');
}

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

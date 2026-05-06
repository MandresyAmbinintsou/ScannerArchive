<?php
// ============================================================
// public/image.php — Sert une image depuis le disque local
// Appel : /public/image.php?id=123
// ============================================================

require_once __DIR__ . '/../config/database.php';

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

// Sécurité : vérifier que le chemin est bien dans ARCHIVE_ROOT
if (strpos(realpath($path), realpath(ARCHIVE_ROOT)) !== 0) {
    http_response_code(403);
    exit('Accès refusé');
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
];
$mime = $mimeMap[$ext] ?? 'application/octet-stream';

// Cache navigateur (1 heure)
header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=3600');
header('Content-Length: ' . filesize($path));

readfile($path);

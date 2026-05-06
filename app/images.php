<?php
// ============================================================
// api/images.php — Images d'un sous-dossier
// Appel : /api/images.php?sousdossier_id=12
// ============================================================

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $sousDossierId = (int)($_GET['sousdossier_id'] ?? 0);
    if ($sousDossierId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'sousdossier_id requis']);
        exit;
    }

    $db = getDB();

    $stmt = $db->prepare('
        SELECT id, nom_fichier
        FROM images
        WHERE sousdossier_id = :sid
        ORDER BY nom_fichier
    ');
    $stmt->execute([':sid' => $sousDossierId]);
    $rows = $stmt->fetchAll();

    // Construire les URLs pour chaque image
    $baseUrl = 'app/image.php?id=';
    foreach ($rows as &$row) {
        $row['url'] = $baseUrl . $row['id'];
    }

    echo json_encode(['data' => $rows]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage(),
    ]);
}

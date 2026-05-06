<?php
// ============================================================
// api/sousdossiers.php — Sous-dossiers d'un matricule
// Appel : /api/sousdossiers.php?matricule_id=5
// ============================================================

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $matriculeId = (int)($_GET['matricule_id'] ?? 0);
    if ($matriculeId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'matricule_id requis']);
        exit;
    }

    $db = getDB();

    $stmt = $db->prepare('
        SELECT id, nom, nb_images
        FROM sousdossiers
        WHERE matricule_id = :mid
        ORDER BY nom
    ');
    $stmt->execute([':mid' => $matriculeId]);
    $rows = $stmt->fetchAll();

    echo json_encode(['data' => $rows]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage(),
    ]);
}

<?php
// ============================================================
// api/sousdossiers.php — Sous-dossiers d'un matricule
// Appel : /api/sousdossiers.php?matricule_id=5
// ============================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_login();

try {
    $matriculeId = (int)($_GET['matricule_id'] ?? 0);
    if ($matriculeId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'matricule_id requis']);
        exit;
    }

    $db = getDB();

    $stmt = $db->prepare('
        SELECT
            s.id,
            s.nom,
            s.nb_images,
            i.id AS preview_id,
            i.nom_fichier AS preview_nom
        FROM sousdossiers s
        LEFT JOIN LATERAL (
            SELECT id, nom_fichier
            FROM images
            WHERE sousdossier_id = s.id
            ORDER BY nom_fichier
            LIMIT 1
        ) i ON true
        WHERE s.matricule_id = :mid
        ORDER BY s.nom
    ');
    $stmt->execute([':mid' => $matriculeId]);
    $rows = $stmt->fetchAll();

    // Construire l'URL de preview si dispo (même endpoint que la lightbox)
    foreach ($rows as &$row) {
        if (!empty($row['preview_id'])) {
            $row['preview_url'] = 'app/image.php?id=' . $row['preview_id'];
        } else {
            $row['preview_url'] = null;
        }
    }
    unset($row);

    echo json_encode(['data' => $rows]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage(),
    ]);
}

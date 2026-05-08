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
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(500, max(10, (int)($_GET['limit'] ?? 100)));
    $offset = ($page - 1) * $limit;

    if ($sousDossierId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'sousdossier_id requis']);
        exit;
    }

    $db = getDB();

    // Compter le total pour la pagination
    $stmtCount = $db->prepare('SELECT COUNT(*) FROM images WHERE sousdossier_id = :sid');
    $stmtCount->execute([':sid' => $sousDossierId]);
    $total = (int)$stmtCount->fetchColumn();

    $stmt = $db->prepare('
        SELECT id, nom_fichier
        FROM images
        WHERE sousdossier_id = :sid
        ORDER BY nom_fichier
        LIMIT :limit OFFSET :offset
    ');
    $stmt->bindValue(':sid', $sousDossierId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    // Construire les URLs pour chaque image
    $baseUrl = 'app/image.php?id=';
    foreach ($rows as &$row) {
        $row['url'] = $baseUrl . $row['id'];
    }

    echo json_encode([
        'data' => $rows,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => ceil($total / $limit),
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage(),
    ]);
}

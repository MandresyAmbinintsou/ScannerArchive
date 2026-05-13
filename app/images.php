<?php
// ============================================================
// api/images.php — Images d'un sous-dossier
// Appel : /api/images.php?sousdossier_id=12
// ============================================================

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

function computeParentNom(string $nom): ?string {
    $nom = trim($nom);
    if ($nom === '' || $nom === '.') return null;
    $pos = strrpos($nom, '/');
    if ($pos === false) return '.';
    $parent = substr($nom, 0, $pos);
    return $parent === '' ? '.' : $parent;
}

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

    // Infos du dossier courant (pour navigation type explorateur)
    $stmtCurrent = $db->prepare('SELECT id, matricule_id, nom FROM sousdossiers WHERE id = :sid');
    $stmtCurrent->execute([':sid' => $sousDossierId]);
    $current = $stmtCurrent->fetch(PDO::FETCH_ASSOC);
    if (!$current) {
        http_response_code(404);
        echo json_encode(['error' => 'Sous-dossier introuvable']);
        exit;
    }

    $matriculeId = (int)$current['matricule_id'];
    $currentNom = (string)$current['nom'];

    // Parent (si existe)
    $parentNom = computeParentNom($currentNom);
    $parent = null;
    if ($parentNom !== null) {
        $stmtParent = $db->prepare('SELECT id, nom FROM sousdossiers WHERE matricule_id = :mid AND nom = :nom LIMIT 1');
        $stmtParent->execute([':mid' => $matriculeId, ':nom' => $parentNom]);
        $parentRow = $stmtParent->fetch(PDO::FETCH_ASSOC);
        if ($parentRow) {
            $parent = [
                'id' => (int)$parentRow['id'],
                'nom' => (string)$parentRow['nom'],
            ];
        }
    }

    // Sous-dossiers "enfants" directs (navigation)
    $folders = [];
    if ($currentNom === '.') {
        $stmtFolders = $db->prepare("
            SELECT id, nom, nb_images
            FROM sousdossiers
            WHERE matricule_id = :mid
              AND nom <> '.'
              AND POSITION('/' IN nom) = 0
            ORDER BY nom
        ");
        $stmtFolders->execute([':mid' => $matriculeId]);
        $folders = $stmtFolders->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $prefix = $currentNom . '/';
        $like = $prefix . '%';
        $start = strlen($prefix) + 1; // 1-based pour SUBSTRING en PostgreSQL

        $stmtFolders = $db->prepare("
            SELECT id, nom, nb_images
            FROM sousdossiers
            WHERE matricule_id = :mid
              AND nom LIKE :like
              AND nom <> :currentNom
              AND POSITION('/' IN substr(nom, :start)) = 0
            ORDER BY nom
        ");
        $stmtFolders->bindValue(':mid', $matriculeId, PDO::PARAM_INT);
        $stmtFolders->bindValue(':like', $like, PDO::PARAM_STR);
        $stmtFolders->bindValue(':currentNom', $currentNom, PDO::PARAM_STR);
        $stmtFolders->bindValue(':start', $start, PDO::PARAM_INT);
        $stmtFolders->execute();
        $folders = $stmtFolders->fetchAll(PDO::FETCH_ASSOC);
    }

    // Ajuster l'affichage des noms (label = segment direct)
    $prefixLen = $currentNom === '.' ? 0 : strlen($currentNom) + 1;
    foreach ($folders as &$f) {
        $f['id'] = (int)$f['id'];
        $f['nb_images'] = (int)$f['nb_images'];
        $fullNom = (string)$f['nom'];
        $f['label'] = $prefixLen > 0 ? substr($fullNom, $prefixLen) : $fullNom;
    }
    unset($f);

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
        'current' => [
            'id' => (int)$current['id'],
            'nom' => $currentNom,
        ],
        'parent' => $parent,
        'folders' => $folders,
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

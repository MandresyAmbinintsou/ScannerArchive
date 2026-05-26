<?php
// ============================================================
// api/folder.php — Contenu d'un dossier (style explorateur)
// Appel : /app/folder.php?matricule_id=5&nom=POIRE&page=1&limit=100
// - Retourne les sous-dossiers directs + les images du dossier courant
// - Ajoute une preview (1ère image) trouvée dans le sous-arbre du dossier enfant
// ============================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_login();

function computeParentNom(string $nom): ?string {
    $nom = trim($nom);
    if ($nom === '' || $nom === '.') return null;
    $pos = strrpos($nom, '/');
    if ($pos === false) return '.';
    $parent = substr($nom, 0, $pos);
    return $parent === '' ? '.' : $parent;
}

try {
    $matriculeId = (int)($_GET['matricule_id'] ?? 0);
    $nom = trim((string)($_GET['nom'] ?? '.'));
    if ($nom === '') $nom = '.';

    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(500, max(10, (int)($_GET['limit'] ?? 100)));
    $offset = ($page - 1) * $limit;

    if ($matriculeId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'matricule_id requis']);
        exit;
    }

    $db = getDB();

    // Dossier courant (il devrait exister car le scan insère tous les dirs)
    $stmtCurrent = $db->prepare('SELECT id, nom, nb_images FROM sousdossiers WHERE matricule_id = :mid AND nom = :nom LIMIT 1');
    $stmtCurrent->execute([':mid' => $matriculeId, ':nom' => $nom]);
    $current = $stmtCurrent->fetch(PDO::FETCH_ASSOC);
    if (!$current) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Dossier introuvable']);
        exit;
    }

    $currentId = (int)$current['id'];

    // Sous-dossiers directs
    if ($nom === '.') {
        $stmtFolders = $db->prepare("
            SELECT id, nom, nb_images, modifie_le
            FROM sousdossiers
            WHERE matricule_id = :mid
              AND nom <> '.'
              AND POSITION('/' IN nom) = 0
            ORDER BY nom
        ");
        $stmtFolders->execute([':mid' => $matriculeId]);
    } else {
        $prefix = $nom . '/';
        $like = $prefix . '%';
        $start = strlen($prefix) + 1; // 1-based
        $stmtFolders = $db->prepare("
            SELECT id, nom, nb_images, modifie_le
            FROM sousdossiers
            WHERE matricule_id = :mid
              AND nom LIKE :like
              AND nom <> :nom
              AND POSITION('/' IN substr(nom, :start)) = 0
            ORDER BY nom
        ");
        $stmtFolders->bindValue(':mid', $matriculeId, PDO::PARAM_INT);
        $stmtFolders->bindValue(':like', $like, PDO::PARAM_STR);
        $stmtFolders->bindValue(':nom', $nom, PDO::PARAM_STR);
        $stmtFolders->bindValue(':start', $start, PDO::PARAM_INT);
        $stmtFolders->execute();
    }

    $folders = $stmtFolders->fetchAll(PDO::FETCH_ASSOC);
    $prefixLen = $nom === '.' ? 0 : (strlen($nom) + 1);
    foreach ($folders as &$f) {
        $f['id'] = (int)$f['id'];
        $f['nb_images'] = (int)$f['nb_images'];
        $fullNom = (string)$f['nom'];
        $f['label'] = $prefixLen > 0 ? substr($fullNom, $prefixLen) : $fullNom;

        // Preview (1ère image trouvée dans le sous-arbre du dossier enfant)
        $stmtPrev = $db->prepare("
            SELECT i.id, i.nom_fichier
            FROM images i
            JOIN sousdossiers s2 ON s2.id = i.sousdossier_id
            WHERE s2.matricule_id = :mid
              AND (s2.nom = :child OR s2.nom LIKE :childLike)
            ORDER BY i.nom_fichier
            LIMIT 1
        ");
        $stmtPrev->execute([
            ':mid' => $matriculeId,
            ':child' => $fullNom,
            ':childLike' => $fullNom . '/%',
        ]);
        $prev = $stmtPrev->fetch(PDO::FETCH_ASSOC);
        if ($prev) {
            $f['preview_id'] = (int)$prev['id'];
            $f['preview_url'] = 'app/image.php?id=' . (int)$prev['id'];
            $f['preview_nom'] = (string)$prev['nom_fichier'];
        } else {
            $f['preview_id'] = null;
            $f['preview_url'] = null;
            $f['preview_nom'] = null;
        }
    }
    unset($f);

    // Images directes du dossier courant
    $stmtCount = $db->prepare('SELECT COUNT(*) FROM images WHERE sousdossier_id = :sid');
    $stmtCount->execute([':sid' => $currentId]);
    $total = (int)$stmtCount->fetchColumn();

    $stmtImgs = $db->prepare('
        SELECT id, nom_fichier
        FROM images
        WHERE sousdossier_id = :sid
        ORDER BY nom_fichier
        LIMIT :limit OFFSET :offset
    ');
    $stmtImgs->bindValue(':sid', $currentId, PDO::PARAM_INT);
    $stmtImgs->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmtImgs->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtImgs->execute();
    $images = $stmtImgs->fetchAll(PDO::FETCH_ASSOC);
    foreach ($images as &$img) {
        $img['id'] = (int)$img['id'];
        $img['url'] = 'app/image.php?id=' . (int)$img['id'];
    }
    unset($img);

    $parentNom = computeParentNom($nom);
    $parent = null;
    if ($parentNom !== null) {
        $stmtParent = $db->prepare('SELECT id, nom FROM sousdossiers WHERE matricule_id = :mid AND nom = :nom LIMIT 1');
        $stmtParent->execute([':mid' => $matriculeId, ':nom' => $parentNom]);
        $p = $stmtParent->fetch(PDO::FETCH_ASSOC);
        if ($p) {
            $parent = [
                'id' => (int)$p['id'],
                'nom' => (string)$p['nom'],
            ];
        }
    }

    echo json_encode([
        'ok' => true,
        'current' => ['id' => $currentId, 'nom' => $nom],
        'parent' => $parent,
        'folders' => $folders,
        'images' => $images,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int)ceil($total / $limit),
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}

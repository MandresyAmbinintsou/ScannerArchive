<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=30'); // Cache navigateur 30s

require_login();

try {
    $start = microtime(true);

    $page   = max(1, (int)($_GET['page']  ?? 1));
    $limit  = min(200, max(10, (int)($_GET['limit'] ?? 50)));
    $search = trim($_GET['q'] ?? '');
    $offset = ($page - 1) * $limit;

    $db = getDB();

// PostgreSQL: pas de query_cache_type

    if ($search !== '') {
        // ILIKE est spécifique à PostgreSQL et permet une recherche insensible à la casse
        $countSql = 'SELECT COUNT(*) FROM matricules WHERE nom ILIKE ?';
        $dataSql  = '
            SELECT id, nom, nb_sousdossiers
            FROM matricules
            WHERE nom ILIKE ?
            ORDER BY nom
            LIMIT ? OFFSET ?
        ';
        $searchParam = '%' . $search . '%';

        $stmtCount = $db->prepare($countSql);
        $stmtCount->execute([$searchParam]);
        $total = (int)$stmtCount->fetchColumn();

        // Pagination conditionnelle pour éviter OFFSET sur les dernières pages
        $stmt = $db->prepare($dataSql);
        $stmt->bindValue(1, $searchParam, PDO::PARAM_STR);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $total = (int)$db->query('SELECT COUNT(*) FROM matricules')->fetchColumn();

        $stmt = $db->prepare('SELECT id, nom, nb_sousdossiers FROM matricules ORDER BY nom LIMIT ? OFFSET ?');
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
    }

    $rows = $stmt->fetchAll();
    $queryTime = round((microtime(true) - $start) * 1000);

    echo json_encode([
        'data'       => $rows,
        'pagination' => [
            'page'       => $page,
            'limit'      => $limit,
            'total'      => $total,
            'totalPages' => ceil($total / $limit),
        ],
        'search' => $search,
        'queryTime' => $queryTime . 'ms', // Pour le debug
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage(),
    ]);
}

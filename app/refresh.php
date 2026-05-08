<?php
// ============================================================
// refresh.php — API de réindexation pour mettre à jour le contenu après F5
// ============================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/scan.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $db = getDB();
    $requestedRoot = trim($_GET['root'] ?? '');

    if ($requestedRoot !== '') {
        $archiveRoot = validatePath($requestedRoot);
    } else {
        $archiveRoot = getLastScannedRoot($db) ?: ARCHIVE_ROOT;
        $archiveRoot = validatePath($archiveRoot);
    }

    $summary = scanArchive($db, $archiveRoot);

    $notifyFile = __DIR__ . '/.notify.json';
    $notification = [
        'type' => 'finish',
        'message' => 'Nouveaux matricules indexés',
        'timestamp' => time(),
    ];
    @file_put_contents($notifyFile, json_encode($notification));

    echo json_encode([
        'ok'      => true,
        'root'    => $archiveRoot,
        'message' => 'Indexation terminée.',
        'summary' => $summary,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'message' => 'Erreur lors de l\'indexation : ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

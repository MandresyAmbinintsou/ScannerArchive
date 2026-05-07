<?php
/**
 * swoole_server.php
 * Serveur HTTP et WebSocket haute performance pour Archive Viewer
 */

use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\WebSocket\Server;
use Swoole\WebSocket\Frame;

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/scan.php';

// Configuration du serveur
$server = new Server("0.0.0.0", 8000);

$server->set([
    'worker_num' => 4,
    'task_worker_num' => 2,
    'enable_static_handler' => true,
    'document_root' => realpath(__DIR__ . '/..'),
]);

// --- Gestion WebSocket ---
$server->on('Open', function (Server $server, Request $request) {
    echo "Nouvelle connexion WebSocket : {$request->fd}\n";
});

// Timer pour scan automatique toutes les 5 minutes
$server->on('WorkerStart', function(Server $server, $workerId) {
    if ($workerId === 0) {
        Swoole\Timer::tick(300000, function() use ($server) {
            echo "[" . date('H:i:s') . "] Scan automatique planifié...\n";
            $server->task(['root' => ARCHIVE_ROOT]);
        });
    }
});

$server->on('Message', function (Server $server, Frame $frame) {
    $data = json_decode($frame->data, true);
    if (($data['action'] ?? '') === 'start_scan') {
        $server->push($frame->fd, json_encode(['type' => 'status', 'message' => 'Lancement du scan...']));
        $server->task([
            'fd' => $frame->fd,
            'root' => $data['root'] ?? ARCHIVE_ROOT
        ]);
    }
});

// --- Gestion HTTP (API) ---
$server->on('Request', function (Request $request, Response $response) {
    $uri = $request->server['request_uri'];
    
    if ($uri === '/app/matricules.php') {
        $db = getDB();
        $page = (int)($request->get['page'] ?? 1);
        $limit = (int)($request->get['limit'] ?? 50);
        $offset = ($page - 1) * $limit;
        
        $stmt = $db->prepare('SELECT id, nom, nb_sousdossiers FROM matricules ORDER BY nom LIMIT ? OFFSET ?');
        $stmt->execute([$limit, $offset]);
        $rows = $stmt->fetchAll();
        
        $total = $db->query('SELECT COUNT(*) FROM matricules')->fetchColumn();
        
        $response->header('Content-Type', 'application/json');
        $response->end(json_encode([
            'data' => $rows,
            'pagination' => [
                'total' => (int)$total,
                'totalPages' => ceil($total / $limit),
                'page' => $page
            ]
        ]));
        return;
    }

    $file = realpath(__DIR__ . '/..' . $uri);
    if ($file && is_file($file)) {
        $response->sendfile($file);
    } else {
        $response->sendfile(realpath(__DIR__ . '/../index.php'));
    }
});

// --- Gestion des Tâches Lourdes (le Scan) ---
$server->on('Task', function (Server $server, $task_id, $from_id, $data) {
    $fd = $data['fd'] ?? null;
    $archiveRoot = $data['root'];
    $shouldBroadcast = $data['broadcast'] ?? false;
    $db = getDB();
    
    $msg = ['type' => 'progress', 'percent' => 10, 'message' => 'Lecture du dossier racine...'];
    if ($shouldBroadcast) {
        broadcast($server, $msg);
    } elseif ($fd && $server->exist($fd)) {
        $server->push($fd, json_encode($msg));
    }

    try {
        $summary = scanArchive($db, $archiveRoot); 
        
        $finishMsg = [
            'type' => 'finish',
            'message' => 'Indexation terminée',
            'summary' => $summary
        ];
        
        if ($shouldBroadcast) {
            broadcast($server, $finishMsg);
        } elseif ($fd && $server->exist($fd)) {
            $server->push($fd, json_encode($finishMsg));
        }
    } catch (Exception $e) {
        $errorMsg = ['type' => 'error', 'message' => $e->getMessage()];
        if ($shouldBroadcast) {
            broadcast($server, $errorMsg);
        } elseif ($fd && $server->exist($fd)) {
            $server->push($fd, json_encode($errorMsg));
        }
    }
});

$server->on('Finish', function (Server $server, $task_id, $data) {
    echo "Tâche de scan #$task_id terminée.\n";
});

echo "Serveur démarré sur http://localhost:8000\n";
$server->start();
art();

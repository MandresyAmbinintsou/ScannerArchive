<?php
/**
 * workerman_server.php - Serveur Workerman Simple et Fiable
 * Alternative Windows à Swoole - Compatible avec votre app
 * Port 8001 : WebSocket notifications
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/scan.php';

use Workerman\Worker;
use Workerman\Timer;

echo "\n=== Archive Viewer - Serveur Workerman ===\n\n";

// ============================================================
// Serveur WebSocket Principal (Port 8001)
// ============================================================
$wsWorker = new Worker("websocket://0.0.0.0:8001");
$wsWorker->count = 1; // Limitation Windows : un seul processus
$wsWorker->name = 'ArchiveViewerWS';

// Propriétés partagées
$wsWorker->clients = [];

// Événement : connexion établie
$wsWorker->onWebSocketConnect = function($connection) use ($wsWorker) {
    echo "[✓] Client WebSocket connecté : {$connection->id}\n";
    $wsWorker->clients[$connection->id] = $connection;
    
    // Envoyer statut initial
    $connection->send(json_encode([
        'type' => 'connected',
        'message' => 'Connecté au serveur Workerman',
        'timestamp' => date('H:i:s')
    ]));
};

// Événement : message reçu
$wsWorker->onMessage = function($connection, $data) use ($wsWorker) {
    $msg = @json_decode($data, true);
    if (!is_array($msg)) return;
    
    if (($msg['action'] ?? '') === 'start_scan') {
        echo "[→] Scan demandé par le client\n";
        broadcastNotification($wsWorker, 'progress', 'Scan en cours...');
    }
};

// Événement : connexion fermée
$wsWorker->onClose = function($connection) use ($wsWorker) {
    unset($wsWorker->clients[$connection->id]);
    echo "[✗] Client WebSocket déconnecté : {$connection->id}\n";
};

$notificationFile = __DIR__ . '/.notify.json';
$lastNotificationTime = 0;

function broadcastNotification($wsWorker, $type, $message) {
    $payload = json_encode(['type' => $type, 'message' => $message]);
    foreach ($wsWorker->clients as $client) {
        @$client->send($payload);
    }
}

// Événement : worker démarré
$wsWorker->onWorkerStart = function($worker) use ($wsWorker, $notificationFile, &$lastNotificationTime) {
    echo "[✓] Serveur WebSocket lancé sur ws://0.0.0.0:8001\n";
    echo "[✓] Accédez à votre interface via WAMP (ex: http://localhost/GED-MEF)\n";
    
    // --- WATCHER DÉSACTIVÉ ---
    // Nous ne surveillons plus les dossiers automatiquement pour éviter de mélanger les images
    // des différents répertoires indexés manuellement par l'utilisateur.

    // Vérifier les notifications créées par l'API (ex: refresh.php ou indexer.php)
    Timer::add(1, function() use ($wsWorker, $notificationFile, &$lastNotificationTime) {
        if (!file_exists($notificationFile)) return;
        
        $content = @file_get_contents($notificationFile);
        $payload = @json_decode($content, true);
        
        if (!is_array($payload) || empty($payload['timestamp'])) return;
        if ($payload['timestamp'] <= $lastNotificationTime) return;
        
        $lastNotificationTime = $payload['timestamp'];
        broadcastNotification($wsWorker, $payload['type'] ?? 'status', $payload['message'] ?? 'Mise à jour');
        
        // On garde le fichier mais on sait qu'on l'a déjà traité grâce au timestamp
    });
};

// ============================================================
// HANDLERS DES APIs (Si utilisé comme serveur standalone)
// ============================================================

function handleMatricules($connection, $queryString) {
    parse_str($queryString, $params);
    try {
        $page = max(1, (int)($params['page'] ?? 1));
        $limit = min(200, max(10, (int)($params['limit'] ?? 50)));
        $search = trim($params['q'] ?? '');
        $offset = ($page - 1) * $limit;
        
        $db = getDB();
        
        if ($search !== '') {
            $countStmt = $db->prepare('SELECT COUNT(*) FROM matricules WHERE nom ILIKE ?');
            $countStmt->execute(['%' . $search . '%']);
            $total = (int)$countStmt->fetchColumn();
            
            $stmt = $db->prepare('SELECT id, nom, nb_sousdossiers FROM matricules WHERE nom ILIKE ? ORDER BY nom LIMIT ? OFFSET ?');
            $stmt->execute(['%' . $search . '%', $limit, $offset]);
        } else {
            $total = (int)$db->query('SELECT COUNT(*) FROM matricules')->fetchColumn();
            $stmt = $db->prepare('SELECT id, nom, nb_sousdossiers FROM matricules ORDER BY nom LIMIT ? OFFSET ?');
            $stmt->execute([$limit, $offset]);
        }
        
        sendJson($connection, [
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'totalPages' => ceil($total / $limit)]
        ]);
    } catch (Exception $e) {
        sendJson($connection, ['error' => $e->getMessage()], 500);
    }
}

function handleSousdossiers($connection, $queryString) {
    parse_str($queryString, $params);
    $mid = (int)($params['matricule_id'] ?? 0);
    if ($mid <= 0) return sendJson($connection, ['error' => 'ID requis'], 400);
    
    try {
        $db = getDB();
        $stmt = $db->prepare('SELECT id, nom, nb_images FROM sousdossiers WHERE matricule_id = ? ORDER BY nom');
        $stmt->execute([$mid]);
        sendJson($connection, ['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        sendJson($connection, ['error' => $e->getMessage()], 500);
    }
}

function handleImages($connection, $queryString) {
    parse_str($queryString, $params);
    $sid = (int)($params['sousdossier_id'] ?? 0);
    if ($sid <= 0) return sendJson($connection, ['error' => 'ID requis'], 400);
    
    try {
        $db = getDB();
        $stmt = $db->prepare('SELECT id, nom_fichier FROM images WHERE sousdossier_id = ? ORDER BY nom_fichier');
        $stmt->execute([$sid]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($rows as &$row) {
            $row['url'] = 'app/image.php?id=' . $row['id'];
        }
        sendJson($connection, ['data' => $rows]);
    } catch (Exception $e) {
        sendJson($connection, ['error' => $e->getMessage()], 500);
    }
}

function sendJson($connection, $data, $code = 200) {
    $json = json_encode($data);
    $response = "HTTP/1.1 $code OK\r\n";
    $response .= "Content-Type: application/json; charset=utf-8\r\n";
    $response .= "Content-Length: " . strlen($json) . "\r\n";
    $response .= "Access-Control-Allow-Origin: *\r\n\r\n" . $json;
    $connection->send($response);
}

// Lancement
Worker::runAll();

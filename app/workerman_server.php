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
$wsWorker->count = 1; // Windows limitation
$wsWorker->name = 'ArchiveViewerWS';

// Propriétés partagées
$wsWorker->clients = [];
$wsWorker->scanStatus = ['status' => 'idle', 'progress' => 0];

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
        
        foreach ($wsWorker->clients as $client) {
            @$client->send(json_encode([
                'type' => 'progress',
                'message' => 'Scan en cours...',
                'progress' => 25
            ]));
        }
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
    echo "[✓] Accédez à http://localhost:8000 pour l'interface PHP\n";
    
    // Scan automatique toutes les 5 minutes
    Timer::add(300, function() use ($worker) {
        echo "[→] Scan automatique lancé\n";

        $startMsg = [
            'type' => 'progress',
            'message' => 'Scan automatique en cours...',
            'progress' => 10
        ];
        foreach ($worker->clients as $client) {
            @$client->send(json_encode($startMsg));
        }

        try {
            $summary = scanArchive(getDB(), ARCHIVE_ROOT);
            echo "[✓] Scan automatique terminé\n";
            broadcastNotification($worker, 'finish', 'Indexation automatique terminée - nouveaux matricules disponibles');

            // Écrire aussi le fichier de notification pour compatibilité
            @file_put_contents($notificationFile, json_encode([
                'type' => 'finish',
                'message' => 'Indexation automatique terminée - nouveaux matricules disponibles',
                'timestamp' => time(),
            ]));
        } catch (Exception $e) {
            echo "[✗] Erreur lors du scan automatique : {$e->getMessage()}\n";
            broadcastNotification($worker, 'error', 'Erreur du scan automatique : ' . $e->getMessage());
        }
    });

    // Vérifier les notifications créées par l'API de refresh
    Timer::add(1, function() use ($wsWorker, $notificationFile, &$lastNotificationTime) {
        if (!file_exists($notificationFile)) {
            return;
        }
        $payload = @json_decode(file_get_contents($notificationFile), true);
        if (!is_array($payload) || empty($payload['timestamp'])) {
            return;
        }
        if ($payload['timestamp'] <= $lastNotificationTime) {
            return;
        }
        $lastNotificationTime = $payload['timestamp'];
        broadcastNotification($wsWorker, $payload['type'] ?? 'status', $payload['message'] ?? 'Mise à jour disponible');
        @unlink($notificationFile);
    });
};

// ============================================================
// HANDLERS DES APIs
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
        
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendJson($connection, [
            'data' => $rows,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'totalPages' => ceil($total / $limit),
            ],
            'search' => $search,
        ]);
    } catch (Exception $e) {
        sendJson($connection, ['error' => $e->getMessage()], 500);
    }
}

function handleSousdossiers($connection, $queryString) {
    parse_str($queryString, $params);
    $matriculeId = (int)($params['matricule_id'] ?? 0);
    
    if ($matriculeId <= 0) {
        sendJson($connection, ['error' => 'matricule_id requis'], 400);
        return;
    }
    
    try {
        $db = getDB();
        $stmt = $db->prepare('SELECT id, nom, nb_images FROM sousdossiers WHERE matricule_id = ? ORDER BY nom');
        $stmt->execute([$matriculeId]);
        
        sendJson($connection, ['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        sendJson($connection, ['error' => $e->getMessage()], 500);
    }
}

function handleImages($connection, $queryString) {
    parse_str($queryString, $params);
    $sousDossierId = (int)($params['sousdossier_id'] ?? 0);
    
    if ($sousDossierId <= 0) {
        sendJson($connection, ['error' => 'sousdossier_id requis'], 400);
        return;
    }
    
    try {
        $db = getDB();
        $stmt = $db->prepare('SELECT id, nom_fichier, url FROM images WHERE sousdossier_id = ? ORDER BY nom_fichier');
        $stmt->execute([$sousDossierId]);
        
        sendJson($connection, ['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        sendJson($connection, ['error' => $e->getMessage()], 500);
    }
}

// ============================================================
// UTILITAIRES HTTP
// ============================================================

function sendJson($connection, $data, $code = 200) {
    $json = json_encode($data);
    
    $response = "HTTP/1.1 $code OK\r\n";
    $response .= "Content-Type: application/json; charset=utf-8\r\n";
    $response .= "Content-Length: " . strlen($json) . "\r\n";
    $response .= "Cache-Control: public, max-age=30\r\n";
    $response .= "Access-Control-Allow-Origin: *\r\n";
    $response .= "Access-Control-Allow-Methods: GET, POST, OPTIONS\r\n";
    $response .= "\r\n" . $json;
    
    $connection->send($response);
}

function sendResponse($connection, $message, $code = 200) {
    $response = "HTTP/1.1 $code\r\n";
    $response .= "Content-Type: text/plain; charset=utf-8\r\n";
    $response .= "Content-Length: " . strlen($message) . "\r\n";
    $response .= "\r\n" . $message;
    
    $connection->send($response);
}

function serveStaticFile($connection, $file) {
    if (!file_exists($file)) {
        sendResponse($connection, 'Not Found', 404);
        return;
    }
    
    $content = file_get_contents($file);
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    
    $mimeTypes = [
        'html' => 'text/html; charset=utf-8',
        'js' => 'application/javascript',
        'css' => 'text/css',
        'json' => 'application/json',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'ico' => 'image/x-icon',
    ];
    
    $mime = $mimeTypes[$ext] ?? 'text/plain';
    
    $response = "HTTP/1.1 200 OK\r\n";
    $response .= "Content-Type: $mime\r\n";
    $response .= "Content-Length: " . strlen($content) . "\r\n";
    $response .= ($ext === 'html' ? "Cache-Control: no-cache\r\n" : "Cache-Control: public, max-age=3600\r\n");
    $response .= "\r\n";
    
    $connection->send($response . $content);
}

function isAllowedFile($file) {
    $allowed = [
        'jpg', 'jpeg', 'png', 'gif', 'svg', 'ico',
        'js', 'css', 'woff', 'woff2', 'ttf', 'json',
        'htm', 'html'
    ];
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    return in_array($ext, $allowed);
}

// ============================================================
// DÉMARRAGE
// ============================================================

Worker::runAll();

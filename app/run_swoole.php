<?php
/**
 * app/run_swoole.php
 * Script pour lancer le serveur Swoole en arrière-plan
 */

require_once __DIR__ . '/auth.php';
check_admin();

header('Content-Type: application/json');

function isSwooleRunning($host = '127.0.0.1', $port = 8000) {
    $connection = @fsockopen($host, $port, $errno, $errstr, 1);
    if (is_resource($connection)) {
        fclose($connection);
        return true;
    }
    return false;
}

if (isSwooleRunning()) {
    echo json_encode(['ok' => true, 'message' => 'Swoole est déjà en cours d\'exécution.']);
    exit;
}

$cmd = "php " . escapeshellarg(__DIR__ . '/swoole_server.php');

if (PHP_OS_FAMILY === 'Windows') {
    // Lancer en arrière-plan sous Windows
    pclose(popen("start /B " . $cmd, "r"));
} else {
    // Lancer en arrière-plan sous Linux/Mac
    exec($cmd . " > /dev/null 2>&1 &");
}

// Attendre un court instant pour vérifier si le port s'ouvre
sleep(1);

if (isSwooleRunning()) {
    echo json_encode(['ok' => true, 'message' => 'Serveur lancé avec succès.']);
} else {
    echo json_encode(['ok' => false, 'message' => 'Le serveur a tenté de démarrer mais le port 8000 reste fermé.']);
}

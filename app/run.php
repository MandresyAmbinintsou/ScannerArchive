<?php
/**
 * run.php - Lanceur universel pour Archive Viewer
 * Windows : Lance le serveur PHP intégré (php -S)
 * Linux + Swoole : Lance le serveur haute performance (php swoole_server.php)
 * Linux sans Swoole : Lance le serveur PHP intégré
 */

$port = 8000;
$host = 'localhost';
$isWindows = PHP_OS_FAMILY === 'Windows';
$hasSwoole = extension_loaded('swoole');

echo "=== Archive Viewer Multi-Plateforme ===\n";
echo "OS détecté : " . PHP_OS_FAMILY . "\n";

if (!$isWindows && $hasSwoole) {
    echo "Swoole détecté ! Lancement du serveur haute performance...\n";
    echo "Ouvrez http://$host:$port dans votre navigateur.\n";
    passthru("php app/swoole_server.php");
} else {
    if ($isWindows) {
        echo "Mode Windows détecté (Swoole non supporté nativement).\n";
    } else {
        echo "Swoole non détecté sur Linux. Installation conseillée : pecl install swoole\n";
    }
    echo "Lancement du serveur PHP standard...\n";
    echo "Ouvrez http://$host:$port dans votre navigateur.\n";
    passthru("php -S $host:$port");
}

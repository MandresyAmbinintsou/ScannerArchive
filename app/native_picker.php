<?php
// ============================================================
// native_picker.php — Ouvre le sélecteur natif OS (serveur local)
// Retour: JSON { ok: true, path: "..." } ou { ok:false, message:"..." }
// ============================================================

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

ini_set('display_errors', '0');
error_reporting(E_ALL);

function runCommand(string $command, int $timeoutSeconds = 60): array {
    $proc = proc_open(
        $command,
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes,
        null,
        null,
        ['bypass_shell' => true]
    );

    if (!is_resource($proc)) {
        return ['code' => 1, 'out' => '', 'err' => 'proc_open failed'];
    }

    fclose($pipes[0]);
    stream_set_timeout($pipes[1], $timeoutSeconds);
    stream_set_timeout($pipes[2], $timeoutSeconds);

    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $code = proc_close($proc);
    return ['code' => (int)$code, 'out' => (string)$out, 'err' => (string)$err];
}

try {
    require_once __DIR__ . '/auth.php';
    require_once __DIR__ . '/../config/database.php';
    check_admin();

    $path = '';

    if (PHP_OS_FAMILY === 'Windows') {
        // Utilisation d'un fichier script .ps1 pour éviter les problèmes d'injection et les alertes antivirus.
        $scriptPath = realpath(__DIR__ . '/../scripts/picker.ps1');
        $cmd = "powershell -NoProfile -ExecutionPolicy Bypass -File " . escapeshellarg($scriptPath);
        
        $res = runCommand($cmd);

        if ($res['code'] !== 0) {
            throw new RuntimeException("Le sélecteur n'a pas pu s'ouvrir. Veuillez saisir le chemin manuellement.");
        }
        $path = trim($res['out']);
    } else {
        // Linux / macOS: essayer zenity, puis kdialog.
        $zenity = trim((string)shell_exec('command -v zenity 2>/dev/null')) ?: '';
        $kdialog = trim((string)shell_exec('command -v kdialog 2>/dev/null')) ?: '';

        if ($zenity !== '') {
            $cmd = escapeshellarg($zenity) . ' --file-selection --directory --title=' . escapeshellarg("Choisir le dossier d'archives");
            $res = runCommand($cmd);
            $path = trim($res['out']);
        } elseif ($kdialog !== '') {
            $cmd = escapeshellarg($kdialog) . ' --getexistingdirectory --title ' . escapeshellarg("Choisir le dossier d'archives");
            $res = runCommand($cmd);
            $path = trim($res['out']);
        } else {
            throw new RuntimeException("Aucun picker natif trouvé (installez 'zenity' ou 'kdialog').");
        }
    }

    if ($path === '') {
        echo json_encode(['ok' => false, 'message' => 'Sélection annulée.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // Normaliser et vérifier dossier
    $real = realpath($path);
    if ($real === false || !is_dir($real)) {
        throw new RuntimeException('Dossier invalide: ' . $path);
    }

    echo json_encode(['ok' => true, 'path' => $real], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}


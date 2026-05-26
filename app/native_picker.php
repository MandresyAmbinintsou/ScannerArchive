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

function privileged_file_exists($path) {
    return !empty($path) && file_exists($path);
}

try {
    require_once __DIR__ . '/auth.php';
    require_once __DIR__ . '/../config/database.php';
    check_admin();

    // Protection contre l'accès distant
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    $localIps = ['127.0.0.1', '::1', 'localhost', '::ffff:127.0.0.1'];
    $isLocal = in_array($remoteAddr, $localIps, true);
    
    if (!$isLocal) {
        echo json_encode(['ok' => false, 'message' => 'Accès distant détecté ('.$remoteAddr.'). Le sélecteur natif est réservé au serveur.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $path = '';

    if (PHP_OS_FAMILY === 'Windows') {
        $scriptPath = realpath(__DIR__ . '/../scripts/picker.ps1');
        if (!$scriptPath || !privileged_file_exists($scriptPath)) {
            throw new RuntimeException("Script de sélection introuvable.");
        }
        
        $scriptContent = file_get_contents($scriptPath);
        
        if (function_exists('mb_convert_encoding')) {
            // PowerShell -EncodedCommand attend du UTF-16LE
            $utf16Script = mb_convert_encoding($scriptContent, 'UTF-16LE', 'UTF-8');
            $encodedScript = base64_encode($utf16Script);
            $cmd = "powershell -NoProfile -NonInteractive -ExecutionPolicy Bypass -EncodedCommand " . $encodedScript;
        } else {
            // Fallback si mbstring est manquant
            $cmd = "powershell -NoProfile -NonInteractive -ExecutionPolicy Bypass -File " . escapeshellarg($scriptPath);
        }
        
        $res = runCommand($cmd);

        if ($res['code'] !== 0) {
            $errorDetail = !empty($res['err']) ? $res['err'] : "Erreur inconnue (Code " . $res['code'] . ")";
            $message = "Erreur PowerShell : " . $errorDetail;
            if (stripos($errorDetail, 'denied') !== false || stripos($errorDetail, 'bloqué') !== false) {
                $message .= "\n\nNote : L'exécution semble être bloquée par votre antivirus ou une politique de sécurité. Veuillez utiliser l'explorateur web intégré.";
            }
            throw new RuntimeException($message);
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


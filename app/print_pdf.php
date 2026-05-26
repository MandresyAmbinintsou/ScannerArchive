<?php
/**
 * app/print_pdf.php
 * Convertit une image en PDF à la volée via ImageMagick
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';
ensureSessionStarted();
ini_set('display_errors', '0');
error_reporting(E_ALL);

// On peut passer soit un ID (préféré), soit une URL (ancien système)
$imageId = (int)($_GET['id'] ?? 0);
$imageUrl = $_GET['url'] ?? '';

if ($imageId <= 0 && !empty($imageUrl)) {
    // Tenter d'extraire l'ID de l'URL si format app/image.php?id=XXX
    if (preg_match('/id=(\d+)/', $imageUrl, $matches)) {
        $imageId = (int)$matches[1];
    }
}

if ($imageId <= 0) {
    die("ID d'image manquant ou invalide.");
}

try {
    $db = getDB();
    $stmt = $db->prepare('SELECT chemin_complet, nom_fichier FROM images WHERE id = :id');
    $stmt->execute([':id' => $imageId]);
    $row = $stmt->fetch();

    if (!$row) {
        die("Image non trouvée dans la base de données (ID: $imageId).");
    }

    $localPath = $row['chemin_complet'];
    $nomFichier = $row['nom_fichier'];
} catch (Throwable $e) {
    die("Erreur base de données : " . $e->getMessage());
}

if (!file_exists($localPath)) {
    die("Fichier image introuvable sur le disque : " . $localPath);
}

// Sécurité : vérifier que le chemin est autorisé
$allowedRoot = $_SESSION['archive_root'] ?? ARCHIVE_ROOT;
$realPath = realpath($localPath);
$realAllowed = realpath($allowedRoot);

// Sur Windows, chemins insensibles à la casse.
$startsWithAllowed = (PHP_OS_FAMILY === 'Windows')
    ? ($realPath !== false && $realAllowed !== false && stripos($realPath, $realAllowed) === 0)
    : ($realPath !== false && $realAllowed !== false && strpos($realPath, $realAllowed) === 0);

if (!$startsWithAllowed) {
    die("Accès refusé : le fichier est en dehors du répertoire autorisé.");
}

// Nettoyage du nom de fichier pour le PDF
$filename = pathinfo($nomFichier, PATHINFO_FILENAME) . '.pdf';

// Déterminer le chemin de ImageMagick
$cmdName = MAGICK_PATH;

function checkMagick($cmd) {
    exec(sprintf('%s -version', $cmd), $out, $status);
    return $status === 0;
}

if (!checkMagick($cmdName)) {
    // Fallback 1: 'convert'
    if (checkMagick('convert')) {
        $cmdName = 'convert';
    } else {
        // Fallback 2: Chemins communs sur Windows
        if (PHP_OS_FAMILY === 'Windows') {
            $commonPaths = [
                'C:\Program Files\ImageMagick-7.1.1-Q16-HDRI\magick.exe',
                'C:\Program Files\ImageMagick-7.1.0-Q16-HDRI\magick.exe',
                'C:\Program Files\ImageMagick-7.0.10-Q16\magick.exe',
                'C:\Program Files (x86)\ImageMagick-6.9.12-Q16\convert.exe',
            ];
            // On peut aussi essayer de chercher dynamiquement dans Program Files
            $found = false;
            foreach ($commonPaths as $p) {
                if (file_exists($p)) {
                    $cmdName = $p;
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                die("ImageMagick n'est pas détecté. Veuillez l'ajouter au PATH Windows ou définir MAGICK_PATH dans config/database.php.");
            }
        } else {
            die("ImageMagick n'est pas installé ou n'est pas dans le PATH du système. Veuillez l'installer.");
        }
    }
}

// Forcer le type de contenu PDF
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');

// Exécuter ImageMagick
// On utilise '-' pour envoyer le résultat sur la sortie standard (stdout)
$command = sprintf('%s %s pdf:-', (strpos($cmdName, ' ') !== false ? '"'.$cmdName.'"' : $cmdName), escapeshellarg($localPath));

passthru($command);
exit;

<?php
/**
 * app/print_pdf.php
 * Convertit une image en PDF à la volée via ImageMagick
 */

require_once __DIR__ . '/auth.php';
require_login();

$imageUrl = $_GET['url'] ?? '';

if (empty($imageUrl)) {
    die("URL de l'image manquante.");
}

// Convertir l'URL en chemin local
$baseDir = realpath(__DIR__ . '/..');
// On suppose que l'URL commence par le chemin relatif du projet
// Exemple : /GED-MEF/archive/MAT1/SD1/img.jpg
// On doit extraire la partie après le nom du projet
$parsedUrl = parse_url($imageUrl, PHP_URL_PATH);
$relativeOps = str_replace('/GED-MEF/', '', $parsedUrl);
$localPath = realpath($baseDir . '/' . $relativeOps);

if (!$localPath || !is_file($localPath)) {
    die("Fichier image introuvable localement : " . $localPath);
}

// Nettoyage du nom de fichier pour le PDF
$filename = pathinfo($localPath, PATHINFO_FILENAME) . '.pdf';

// Forcer le type de contenu PDF
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');

// Exécuter ImageMagick (magick)
// On utilise '-' pour envoyer le résultat sur la sortie standard (stdout)
$command = sprintf('magick %s pdf:-', escapeshellarg($localPath));

passthru($command);
exit;

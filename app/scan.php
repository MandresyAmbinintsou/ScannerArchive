<?php
// ============================================================
// scan.php — Moteur de scan PHP ultra-optimisé
// ============================================================

require_once __DIR__ . '/../config/database.php';

function validatePath(string $path): string {
    $real = realpath($path);
    if ($real === false || !is_dir($real)) {
        throw new RuntimeException('Dossier invalide ou inaccessible : ' . $path);
    }
    return $real;
}

function dbDriver(PDO $db): string {
    return (string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
}

function truncateArchiveTables(PDO $db): void {
    $db->exec('TRUNCATE TABLE images, sousdossiers, matricules RESTART IDENTITY CASCADE');
}

function insertAndGetId(PDO $db, string $sql, array $params, string $idColumn = 'id'): int {
    $stmt = $db->prepare($sql . " RETURNING $idColumn");
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function ensureScanHistoryTable(PDO $db): void {
    $db->exec('CREATE TABLE IF NOT EXISTS scan_history (id BIGSERIAL PRIMARY KEY, root_path TEXT NOT NULL, root_mtime BIGINT DEFAULT 0, created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP)');
}

function recordScanRoot(PDO $db, string $archiveRoot): void {
    ensureScanHistoryTable($db);
    $stmt = $db->prepare('INSERT INTO scan_history (root_path) VALUES (:root_path)');
    $stmt->execute([':root_path' => $archiveRoot]);
}

function getLastScannedRoot(PDO $db): ?string {
    ensureScanHistoryTable($db);
    $stmt = $db->query('SELECT root_path FROM scan_history ORDER BY created_at DESC LIMIT 1');
    return $stmt->fetchColumn() ?: null;
}

function getScanHistory(PDO $db, int $limit = 20): array {
    ensureScanHistoryTable($db);
    $stmt = $db->prepare('SELECT root_path, created_at FROM scan_history ORDER BY created_at DESC LIMIT :limit');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Scan PHP optimisé - Ultra rapide
 */
function scanArchive(PDO $db, string $archiveRoot): string {
    $startTime = microtime(true);
    $output = [];
    $output[] = "=== INDEXATION PHP OPTIMISÉE ===";
    $output[] = "Dossier : $archiveRoot";

    try {
        $allowedExts = ['png' => 1, 'jpg' => 1, 'jpeg' => 1, 'gif' => 1, 'webp' => 1];

        // Optimisation 1 : TRUNCATE au lieu de DELETE (instantané)
        truncateArchiveTables($db);

        $totalMat = 0;
        $totalSous = 0;
        $totalImg = 0;

        // Optimisation 2 : Utiliser glob au lieu de readdir (plus rapide)
        $matriculePatterns = glob($archiveRoot . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
        
        $db->beginTransaction();

        foreach ($matriculePatterns as $matPath) {
            $matName = basename($matPath);

            // Compter sous-dossiers rapidement
            $sousCount = count(glob($matPath . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR));

            // Insérer matricule
            $matId = insertAndGetId(
                $db,
                "INSERT INTO matricules (nom, chemin, nb_sousdossiers) VALUES (?, ?, ?)",
                [$matName, $matPath, $sousCount]
            );
            $totalMat++;

            // Traiter tous les sous-dossiers
            foreach (glob($matPath . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) as $sousPath) {
                $sousName = basename($sousPath);

                // Scanner images avec glob (EXTRÊMEMENT rapide)
                $images = [];
                foreach (['*.png', '*.jpg', '*.jpeg', '*.gif', '*.webp'] as $pattern) {
                    $images = array_merge($images, glob($sousPath . DIRECTORY_SEPARATOR . $pattern));
                }
                foreach (['*.PNG', '*.JPG', '*.JPEG', '*.GIF', '*.WEBP'] as $pattern) {
                    $images = array_merge($images, glob($sousPath . DIRECTORY_SEPARATOR . $pattern));
                }
                
                $nbImages = count($images);

                // Insérer sous-dossier
                $sousDossierId = insertAndGetId(
                    $db,
                    "INSERT INTO sousdossiers (matricule_id, nom, chemin, nb_images) VALUES (?, ?, ?, ?)",
                    [$matId, $sousName, $sousPath, $nbImages]
                );
                $totalSous++;
                $totalImg += $nbImages;

                // Optimisation 4 : Bulk insert par batch de 1000
                if ($nbImages > 0) {
                    $stmtImg = $db->prepare("INSERT INTO images (sousdossier_id, nom_fichier, chemin_complet) VALUES (?, ?, ?)");
                    foreach ($images as $imgPath) {
                        $stmtImg->execute([$sousDossierId, basename($imgPath), $imgPath]);
                    }
                }
            }
        }

        $db->commit();

        $elapsed = round(microtime(true) - $startTime, 2);
        $output[] = "";
        $output[] = "=== INDEXATION TERMINÉE ===";
        $output[] = "Matricules : $totalMat";
        $output[] = "Sous-dossiers : $totalSous";
        $output[] = "Images : $totalImg";
        $output[] = "Durée : {$elapsed}s";

        recordScanRoot($db, $archiveRoot);

    } catch (Exception $e) {
        $db->rollBack();
        $output[] = "ERREUR : " . $e->getMessage();
    }

    return implode("\n", $output);
}

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
    try {
        // RESTART IDENTITY est dispo depuis PG 8.4
        $db->exec('TRUNCATE TABLE images, sousdossiers, matricules RESTART IDENTITY CASCADE');
    } catch (PDOException $e) {
        // Fallback pour les versions très anciennes
        $db->exec('TRUNCATE TABLE images, sousdossiers, matricules CASCADE');
    }
}

function insertAndGetId(PDO $db, string $sql, array $params, string $idColumn = 'id'): int {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    // lastInsertId() est compatible avec toutes les versions de PostgreSQL via PDO
    return (int)$db->lastInsertId();
}

function ensureScanHistoryTable(PDO $db): void {
    $stmt = $db->query("SELECT 1 FROM pg_catalog.pg_tables WHERE schemaname = 'public' AND tablename = 'scan_history'");
    if (!$stmt->fetch()) {
        $db->exec('CREATE TABLE scan_history (id BIGSERIAL PRIMARY KEY, root_path TEXT NOT NULL, root_mtime BIGINT DEFAULT 0, created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP)');
    }
}

function recordScanRoot(PDO $db, string $archiveRoot): void {
    ensureScanHistoryTable($db);
    
    // Éviter de rajouter le même chemin s'il est déjà le dernier de l'historique
    $last = getLastScannedRoot($db);
    if ($last === $archiveRoot) {
        // Optionnel : on pourrait mettre à jour created_at pour le faire remonter en haut,
        // mais pour l'instant on se contente d'éviter le doublon immédiat.
        $stmt = $db->prepare('UPDATE scan_history SET created_at = CURRENT_TIMESTAMP WHERE root_path = :root_path AND id = (SELECT id FROM scan_history WHERE root_path = :root_path ORDER BY created_at DESC LIMIT 1)');
        $stmt->execute([':root_path' => $archiveRoot]);
        return;
    }

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
        $allowedExts = [
            'png' => 1,
            'jpg' => 1,
            'jpeg' => 1,
            'gif' => 1,
            'webp' => 1,
            'pdf' => 1,
        ];

        // Optimisation 2 : Utiliser glob au lieu de readdir (plus rapide)
        $matriculePatterns = glob($archiveRoot . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
        
        $db->beginTransaction();

        // Optimisation 1 : TRUNCATE au lieu de DELETE (instantané et rollbackable en Postgres)
        truncateArchiveTables($db);

        $totalMat = 0;
        $totalSous = 0;
        $totalImg = 0;

        foreach ($matriculePatterns as $matPath) {
            $matName = basename($matPath);

            // Lister récursivement tous les dossiers (y compris la racine du matricule).
            $dirs = [$matPath];
            for ($i = 0; $i < count($dirs); $i++) {
                $dir = $dirs[$i];
                try {
                    $it = new FilesystemIterator($dir, FilesystemIterator::SKIP_DOTS);
                } catch (UnexpectedValueException $e) {
                    // Dossier inaccessible (droits / lien cassé) -> ignorer
                    continue;
                }
                foreach ($it as $item) {
                    if ($item->isDir() && !$item->isLink()) {
                        $dirs[] = $item->getPathname();
                    }
                }
            }

            $matPathLen = strlen($matPath);
            $relDirs = [];
            foreach ($dirs as $dir) {
                if ($dir === $matPath) {
                    $rel = '.';
                } else {
                    $rel = substr($dir, $matPathLen + 1);
                    $rel = str_replace(DIRECTORY_SEPARATOR, '/', $rel);
                    if ($rel === '') {
                        $rel = '.';
                    }
                }
                $relDirs[] = ['path' => $dir, 'name' => $rel];
            }
            usort($relDirs, static fn($a, $b) => strcmp($a['name'], $b['name']));

            // Compter sous-dossiers (on inclut la racine '.' pour indexer les fichiers "entre" sous-dossiers).
            $sousCount = count($relDirs);

            // Insérer matricule
            $matId = insertAndGetId(
                $db,
                "INSERT INTO matricules (nom, chemin, nb_sousdossiers) VALUES (?, ?, ?)",
                [$matName, $matPath, $sousCount]
            );
            $totalMat++;

            // Traiter tous les dossiers récursifs (affiche TOUTES les images, quel que soit la profondeur).
            $stmtImg = $db->prepare("INSERT INTO images (sousdossier_id, nom_fichier, chemin_complet) VALUES (?, ?, ?)");
            foreach ($relDirs as $entry) {
                $sousPath = $entry['path'];
                $sousName = $entry['name'];
                if (strlen($sousName) > 150) {
                    $sousName = substr($sousName, -150);
                }

                $images = [];
                try {
                    $it = new FilesystemIterator($sousPath, FilesystemIterator::SKIP_DOTS);
                } catch (UnexpectedValueException $e) {
                    continue;
                }
                foreach ($it as $item) {
                    if ($item->isDir() || $item->isLink()) {
                        continue;
                    }
                    $ext = strtolower(pathinfo($item->getFilename(), PATHINFO_EXTENSION));
                    if (!isset($allowedExts[$ext])) {
                        continue;
                    }
                    $images[] = $item->getPathname();
                }

                $nbImages = count($images);
                $sousDossierId = insertAndGetId(
                    $db,
                    "INSERT INTO sousdossiers (matricule_id, nom, chemin, nb_images) VALUES (?, ?, ?, ?)",
                    [$matId, $sousName, $sousPath, $nbImages]
                );
                $totalSous++;
                $totalImg += $nbImages;

                foreach ($images as $imgPath) {
                    $stmtImg->execute([$sousDossierId, basename($imgPath), $imgPath]);
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

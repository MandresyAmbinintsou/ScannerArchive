<?php
// ============================================================
// scan_go.php — Scanner Go (filesystem-only) + ingestion DB
// ============================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/scan.php';

function goScannerPath(): string {
    $env = getenv('GO_SCANNERFS_PATH');
    if ($env && is_string($env) && trim($env) !== '') {
        return trim($env);
    }

    $paths = [
        __DIR__ . '/../bin/scannerfs.exe',
        __DIR__ . '/../bin/scannerfs',
        __DIR__ . '/../dist/scannerfs-windows-amd64.exe',
        __DIR__ . '/../dist/scannerfs-linux-amd64',
        __DIR__ . '/../scannerfs.exe',
        __DIR__ . '/../scannerfs',
    ];

    if (PHP_OS_FAMILY !== 'Windows') {
        $paths = [
            __DIR__ . '/../bin/scannerfs',
            __DIR__ . '/../dist/scannerfs-linux-amd64',
            __DIR__ . '/../scannerfs',
            __DIR__ . '/../bin/scannerfs.exe',
            __DIR__ . '/../dist/scannerfs-windows-amd64.exe',
            __DIR__ . '/../scannerfs.exe',
        ];
    }

    foreach ($paths as $path) {
        if (is_file($path)) {
            return $path;
        }
    }

    return __DIR__ . '/../bin/scannerfs';
}

function isGoScannerAvailable(): bool {
    $scanner = goScannerPath();
    return is_file($scanner) && is_executable($scanner);
}

function scanArchiveGo(PDO $db, string $archiveRoot, ?string $scannerPath = null): string {
    $startTime = microtime(true);
    $output = [];
    $output[] = "=== INDEXATION GO (FS) + INGEST PHP ===";
    $output[] = "Dossier : $archiveRoot";

    $scanner = $scannerPath ?: goScannerPath();
    if (!is_file($scanner) || !is_executable($scanner)) {
        throw new RuntimeException("Binaire scanner Go introuvable/non exécutable: $scanner");
    }

    $archiveRoot = validatePath($archiveRoot);

    $stmtImg = $db->prepare('INSERT INTO images (sousdossier_id, nom_fichier, chemin_complet) VALUES (?, ?, ?)');

    $command = sprintf('%s --root %s --emit-images', escapeshellarg($scanner), escapeshellarg($archiveRoot));

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
        throw new RuntimeException('Impossible de démarrer le scanner Go.');
    }
    fclose($pipes[0]);

    $matIdByName = [];
    $sousIdByKey = [];
    $lastMatDone = [];

    $totalMat = 0;
    $totalSous = 0;
    $totalImg = 0;
    $warnings = 0;

    // Buffer pour batch insert des images
    $imageBuffer = [];
    $batchSize = 500;

    $db->beginTransaction();
    truncateArchiveTables($db);
    try {
        while (($line = fgets($pipes[1])) !== false) {
            $line = trim($line);
            if ($line === '') continue;

            $evt = json_decode($line, true);
            if (!is_array($evt) || !isset($evt['type'])) {
                $warnings++;
                continue;
            }

            $type = (string)$evt['type'];
            if ($type === 'warn') {
                $warnings++;
                continue;
            }

            if ($type === 'matricule') {
                $m = $evt['matricule'] ?? null;
                if (!is_array($m)) { $warnings++; continue; }
                $name = (string)($m['name'] ?? '');
                $path = (string)($m['path'] ?? '');
                $sousCount = (int)($m['sous_count'] ?? 0);
                if ($name === '' || $path === '') { $warnings++; continue; }

                // Tolérer les doublons : si le matricule existe déjà, on met à jour et on récupère son id.
                $stmtUpsertMat = $db->prepare(
                    'INSERT INTO matricules (nom, chemin, nb_sousdossiers)
                     VALUES (?, ?, ?)
                     ON CONFLICT (nom)
                     DO UPDATE SET chemin = EXCLUDED.chemin, nb_sousdossiers = EXCLUDED.nb_sousdossiers, indexe_le = CURRENT_TIMESTAMP
                     RETURNING id'
                );
                $stmtUpsertMat->execute([$name, $path, $sousCount]);
                $matIdByName[$name] = (int)$stmtUpsertMat->fetchColumn();
                $totalMat++;
                continue;
            }

            if ($type === 'sousdossier') {
                $s = $evt['sousdossier'] ?? null;
                if (!is_array($s)) { $warnings++; continue; }
                $matName = (string)($s['matricule_name'] ?? '');
                $name = (string)($s['name'] ?? '');
                $path = (string)($s['path'] ?? '');
                $nbImages = (int)($s['images_count'] ?? 0);
                if ($matName === '' || $name === '' || $path === '') { $warnings++; continue; }

                $matId = $matIdByName[$matName] ?? null;
                if (!$matId) { $warnings++; continue; }

                $sousId = insertAndGetId(
                    $db,
                    'INSERT INTO sousdossiers (matricule_id, nom, chemin, nb_images) VALUES (?, ?, ?, ?)',
                    [$matId, $name, $path, $nbImages]
                );
                $sousIdByKey[$matName . "\n" . $name] = $sousId;
                $totalSous++;
                continue;
            }

            if ($type === 'image') {
                $img = $evt['image'] ?? null;
                if (!is_array($img)) { $warnings++; continue; }
                $matName = (string)$img['matricule_name'];
                $sousName = (string)$img['sous_name'];
                $fileName = (string)$img['file_name'];
                $fullPath = (string)$img['full_path'];

                $sousId = $sousIdByKey[$matName . "\n" . $sousName] ?? null;
                if ($sousId) {
                    $imageBuffer[] = $sousId;
                    $imageBuffer[] = $fileName;
                    $imageBuffer[] = $fullPath;

                    if (count($imageBuffer) >= $batchSize * 3) {
                        $placeholders = str_repeat('(?, ?, ?),', ($batchSize - 1)) . '(?, ?, ?)';
                        $stmt = $db->prepare("INSERT INTO images (sousdossier_id, nom_fichier, chemin_complet) VALUES $placeholders");
                        $stmt->execute($imageBuffer);
                        $imageBuffer = [];
                    }
                } else {
                    $warnings++;
                }
                $totalImg++;
                continue;
            }

            if ($type === 'matricule_done') {
                continue;
            }
        }

        // Vider le buffer restant
        if (!empty($imageBuffer)) {
            $count = count($imageBuffer) / 3;
            $placeholders = str_repeat('(?, ?, ?),', ($count - 1)) . '(?, ?, ?)';
            $stmt = $db->prepare("INSERT INTO images (sousdossier_id, nom_fichier, chemin_complet) VALUES $placeholders");
            $stmt->execute($imageBuffer);
        }

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);
        if ($code !== 0) {
            throw new RuntimeException('Scanner Go a échoué: ' . trim((string)$stderr));
        }

        $db->commit();
        recordScanRoot($db, $archiveRoot);
    } catch (Throwable $e) {
        $db->rollBack();
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_terminate($proc);
        throw $e;
    }

    $elapsed = round(microtime(true) - $startTime, 2);
    $output[] = "";
    $output[] = "=== INDEXATION TERMINÉE ===";
    $output[] = "Matricules : $totalMat";
    $output[] = "Sous-dossiers : $totalSous";
    $output[] = "Images : $totalImg";
    $output[] = "Warnings : $warnings";
    $output[] = "Durée : {$elapsed}s";
    return implode("\n", $output);
}

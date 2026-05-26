<?php
/**
 * app/server_status.php
 * Visualisation de l'état du serveur (Workerman, Système, DB)
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

check_admin();

$pageTitle = "État du Serveur - GED-MEF";
$currentPage = "status";
$baseHref = "../";
require_once __DIR__ . '/header.php';

// Fonctions de diagnostic
function isWorkermanRunning($host = '127.0.0.1', $port = 8001) {
    $connection = @fsockopen($host, $port, $errno, $errstr, 1);
    if (is_resource($connection)) {
        fclose($connection);
        return true;
    }
    return false;
}

$workermanStatus = isWorkermanRunning();
$db = getDB();
$dbStatus = $db ? true : false;

// Logic pour compter les sessions actives de manière robuste
function getActiveSessionsCount() {
    $sessionPath = session_save_path();
    if (empty($sessionPath)) {
        $sessionPath = sys_get_temp_dir();
    }
    
    // Nettoyage pour les chemins PHP Windows (ex: "2;C:\xampp\tmp")
    if (strpos($sessionPath, ';') !== false) {
        $parts = explode(';', $sessionPath);
        $sessionPath = end($parts);
    }

    $sessionPath = rtrim($sessionPath, '/\\');
    $activeSessions = 0;
    $now = time();
    
    // On utilise @glob pour éviter les warnings si le dossier est inaccessible
    $sessionFiles = @glob($sessionPath . DIRECTORY_SEPARATOR . 'sess_*');
    
    if ($sessionFiles) {
        foreach ($sessionFiles as $file) {
            // Une session est considérée active si modifiée il y a moins de 10 minutes (600s)
            // et si le fichier n'est pas vide (évite les sessions fantômes)
            if (is_file($file) && ($now - filemtime($file) < 600) && filesize($file) > 0) {
                $activeSessions++;
            }
        }
    }
    return $activeSessions;
}

// Stats Système (Windows/Linux)
$os = PHP_OS_FAMILY;
$load = "N/A";
if ($os !== 'Windows') {
    $loadArr = sys_getloadavg();
    $load = $loadArr[0] . " (1min)";
}

$memory = round(memory_get_usage() / 1024 / 1024, 2) . " MB";

// Si requête AJAX pour rafraîchir les stats
if (isset($_GET['ajax_stats'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'users' => getActiveSessionsCount(),
        'workerman' => $workermanStatus,
        'memory' => $memory,
        'load' => $load
    ]);
    exit;
}

// ImageMagick check
function checkImageMagick() {
    exec('magick -version', $out, $status);
    if ($status === 0) return ['ok' => true, 'version' => $out[0] ?? 'N/A', 'cmd' => 'magick'];
    exec('convert -version', $out2, $status2);
    if ($status2 === 0) return ['ok' => true, 'version' => $out2[0] ?? 'N/A', 'cmd' => 'convert'];
    return ['ok' => false];
}
$imStatus = checkImageMagick();
?>

<main class="mx-auto max-w-5xl px-4 py-12">
    <div class="mb-12 text-center">
        <h2 class="text-4xl font-black text-slate-900 dark:text-white tracking-tighter uppercase italic">Monitoring Système</h2>
        <p class="mt-2 text-[10px] font-black uppercase tracking-[0.4em] text-indigo-500">Diagnostic en temps réel</p>
    </div>

    <div class="grid gap-8 md:grid-cols-4">
        <!-- Carte Utilisateurs Actifs -->
        <div class="rounded-3xl bg-white dark:bg-slate-800 p-8 shadow-xl border border-slate-100 dark:border-white/5">
            <div class="flex items-center justify-between mb-6">
                <div class="h-12 w-12 rounded-2xl bg-sky-500/10 text-sky-500 flex items-center justify-center">
                    <i class="fas fa-users text-xl"></i>
                </div>
            </div>
            <h3 class="text-xs font-black uppercase text-slate-400 mb-2">Utilisateurs</h3>
            <p id="stat-users" class="text-xl font-black text-slate-900 dark:text-white uppercase">
                <?= getActiveSessionsCount() ?>
            </p>
            <p class="mt-4 text-[9px] font-bold text-slate-500 uppercase">Sessions actives</p>
        </div>

        <!-- Carte Workerman -->
        <div id="card-workerman" class="rounded-3xl bg-white dark:bg-slate-800 p-8 shadow-xl border border-slate-100 dark:border-white/5">
            <div class="flex items-center justify-between mb-6">
                <div id="icon-workerman" class="h-12 w-12 rounded-2xl flex items-center justify-center <?= $workermanStatus ? 'bg-emerald-500/10 text-emerald-500' : 'bg-red-500/10 text-red-500' ?>">
                    <i class="fas fa-bolt text-xl"></i>
                </div>
                <span id="status-workerman" class="text-[8px] font-black uppercase tracking-widest <?= $workermanStatus ? 'text-emerald-500' : 'text-red-500' ?>">
                    <?= $workermanStatus ? 'En ligne' : 'Hors ligne' ?>
                </span>
            </div>
            <h3 class="text-xs font-black uppercase text-slate-400 mb-2">Moteur Temps Réel</h3>
            <p class="text-xl font-black text-slate-900 dark:text-white uppercase">Workerman WebSocket</p>
        </div>

        <!-- Carte Database -->
        <div class="rounded-3xl bg-white dark:bg-slate-800 p-8 shadow-xl border border-slate-100 dark:border-white/5">
            <div class="flex items-center justify-between mb-6">
                <div class="h-12 w-12 rounded-2xl bg-indigo-500/10 text-indigo-500 flex items-center justify-center">
                    <i class="fas fa-database text-xl"></i>
                </div>
                <span class="text-[8px] font-black uppercase tracking-widest text-indigo-500">Connecté</span>
            </div>
            <h3 class="text-xs font-black uppercase text-slate-400 mb-2">Base de données</h3>
            <p class="text-xl font-black text-slate-900 dark:text-white uppercase">PostgreSQL</p>
            <p class="mt-4 text-[9px] font-bold text-indigo-400 uppercase tracking-widest"><?= DB_NAME ?></p>
        </div>

        <!-- Carte Système -->
        <div class="rounded-3xl bg-white dark:bg-slate-800 p-8 shadow-xl border border-slate-100 dark:border-white/5">
            <div class="flex items-center justify-between mb-6">
                <div class="h-12 w-12 rounded-2xl bg-slate-100 dark:bg-slate-900 text-slate-500 flex items-center justify-center">
                    <i class="fas fa-microchip text-xl"></i>
                </div>
                <span class="text-[8px] font-black uppercase tracking-widest text-slate-400"><?= $os ?></span>
            </div>
            <h3 class="text-xs font-black uppercase text-slate-400 mb-2">Utilisation RAM</h3>
            <p id="stat-memory" class="text-xl font-black text-slate-900 dark:text-white uppercase"><?= $memory ?></p>
            <p id="stat-load" class="mt-4 text-[9px] font-bold text-slate-500 uppercase">Load: <?= $load ?></p>
        </div>
    </div>

    <!-- Logiciels & Dépendances -->
    <div class="mt-12">
        <h3 class="text-xs font-black uppercase text-slate-400 mb-6 tracking-widest flex items-center gap-3">
            <i class="fas fa-layer-group text-indigo-500"></i> Logiciels & Dépendances
        </h3>
        <div class="grid gap-8 md:grid-cols-2">
            <!-- ImageMagick Status -->
            <div class="rounded-3xl bg-white dark:bg-slate-800 p-8 shadow-xl border border-slate-100 dark:border-white/5 flex items-center gap-6">
                <div class="h-16 w-16 rounded-2xl flex items-center justify-center shrink-0 <?= $imStatus['ok'] ? 'bg-emerald-500/10 text-emerald-500' : 'bg-red-500/10 text-red-500' ?>">
                    <i class="fas fa-file-pdf text-2xl"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between mb-1">
                        <h4 class="text-sm font-black text-slate-900 dark:text-white uppercase">ImageMagick</h4>
                        <span class="text-[8px] font-black uppercase tracking-widest <?= $imStatus['ok'] ? 'text-emerald-500' : 'text-red-500' ?>">
                            <?= $imStatus['ok'] ? 'Installé' : 'Manquant' ?>
                        </span>
                    </div>
                    <p class="text-[10px] font-bold text-slate-500 truncate">Requis pour la conversion PDF et l'impression.</p>
                    <?php if ($imStatus['ok']): ?>
                        <p class="mt-2 text-[9px] font-mono text-indigo-400 bg-indigo-500/5 px-2 py-1 rounded inline-block"><?= $imStatus['cmd'] ?> version détectée</p>
                    <?php else: ?>
                        <p class="mt-2 text-[9px] font-bold text-red-500 italic">L'impression ne fonctionnera pas sans ce logiciel.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- GD Library Status -->
            <div class="rounded-3xl bg-white dark:bg-slate-800 p-8 shadow-xl border border-slate-100 dark:border-white/5 flex items-center gap-6">
                <div class="h-16 w-16 rounded-2xl bg-indigo-500/10 text-indigo-500 flex items-center justify-center shrink-0">
                    <i class="fas fa-images text-2xl"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between mb-1">
                        <h4 class="text-sm font-black text-slate-900 dark:text-white uppercase">Extension GD</h4>
                        <span class="text-[8px] font-black uppercase tracking-widest text-emerald-500">Actif</span>
                    </div>
                    <p class="text-[10px] font-bold text-slate-500">Gestion des images et miniatures PHP.</p>
                    <p class="mt-2 text-[9px] font-mono text-indigo-400 bg-indigo-500/5 px-2 py-1 rounded inline-block"><?= function_exists('gd_info') ? 'Version ' . gd_info()['GD Version'] : 'N/A' ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <?php if (!$workermanStatus): ?>
    <div class="mt-12 rounded-3xl bg-slate-900 p-10 text-center border border-white/5">
        <h3 class="text-lg font-black text-white uppercase mb-4 tracking-tighter">Relancer le moteur temps réel</h3>
        <p class="text-xs text-slate-400 mb-8 max-w-lg mx-auto leading-relaxed">Si vous n'avez pas accès au terminal, vous pouvez tenter de démarrer le serveur Workerman en arrière-plan ici.</p>
        <button onclick="startWorkerman()" id="btnStartWorkerman" class="rounded-2xl bg-white px-10 py-5 text-[11px] font-black uppercase tracking-widest text-slate-900 hover:bg-indigo-500 hover:text-white transition shadow-2xl">
            Lancer le serveur
        </button>
    </div>
    <?php endif; ?>
</main>

<script>
async function startWorkerman() {
    const btn = document.getElementById('btnStartWorkerman');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-circle-notch animate-spin mr-3"></i> Lancement...';
    
    try {
        const res = await fetch('<?= $baseHref ?>app/run_swoole.php');
        const data = await res.json();
        if (data.ok) {
            alert("Le serveur Workerman a été lancé en arrière-plan !");
            location.reload();
        } else {
            alert("Erreur : " + data.message);
        }
    } catch (e) {
        alert("Erreur lors de la communication avec le serveur.");
    } finally {
        btn.disabled = false;
        btn.innerText = 'Lancer le serveur';
    }
}

// Rafraîchissement automatique des statistiques
async function refreshStats() {
    try {
        const res = await fetch(window.location.href + (window.location.search ? '&' : '?') + 'ajax_stats=1');
        const data = await res.json();
        
        // Mise à jour Utilisateurs
        const elUsers = document.getElementById('stat-users');
        if (elUsers) elUsers.textContent = data.users;

        // Mise à jour Workerman
        const cardWorkerman = document.getElementById('card-workerman');
        const iconWorkerman = document.getElementById('icon-workerman');
        const statusWorkerman = document.getElementById('status-workerman');
        
        if (statusWorkerman) {
            statusWorkerman.textContent = data.workerman ? 'En ligne' : 'Hors ligne';
            statusWorkerman.className = `text-[8px] font-black uppercase tracking-widest ${data.workerman ? 'text-emerald-500' : 'text-red-500'}`;
        }
        if (iconWorkerman) {
            iconWorkerman.className = `h-12 w-12 rounded-2xl flex items-center justify-center ${data.workerman ? 'bg-emerald-500/10 text-emerald-500' : 'bg-red-500/10 text-red-500'}`;
        }

        // Mise à jour Système
        const elMem = document.getElementById('stat-memory');
        if (elMem) elMem.textContent = data.memory;
        
        const elLoad = document.getElementById('stat-load');
        if (elLoad) elLoad.textContent = 'Load: ' + data.load;

    } catch (e) {
        console.warn("Erreur lors du rafraîchissement des stats");
    }
}

setInterval(refreshStats, 10000); // Toutes les 10 secondes
</script>

<?php require_once __DIR__ . '/footer.php'; ?>

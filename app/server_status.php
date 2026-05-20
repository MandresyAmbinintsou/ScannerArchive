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

// Stats Système (Windows/Linux)
$os = PHP_OS_FAMILY;
$load = "N/A";
if ($os !== 'Windows') {
    $loadArr = sys_getloadavg();
    $load = $loadArr[0] . " (1min)";
}

$memory = round(memory_get_usage() / 1024 / 1024, 2) . " MB";
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
            <p class="text-xl font-black text-slate-900 dark:text-white uppercase">
                <?php 
                $sessionPath = 'C:\xampp\tmp';
                $sessionFiles = glob($sessionPath . '\sess_*');
                $activeSessions = 0;
                $now = time();
                foreach ($sessionFiles as $file) {
                    // Compter comme active si modifiée dans les 30 dernières minutes (1800 secondes)
                    if (is_file($file) && ($now - filemtime($file) < 1800)) {
                        $activeSessions++;
                    }
                }
                echo $activeSessions;
                ?>
            </p>
            <p class="mt-4 text-[9px] font-bold text-slate-500 uppercase">Sessions actives</p>
        </div>

        <!-- Carte Workerman -->
        <div class="rounded-3xl bg-white dark:bg-slate-800 p-8 shadow-xl border border-slate-100 dark:border-white/5">
            <div class="flex items-center justify-between mb-6">
                <div class="h-12 w-12 rounded-2xl flex items-center justify-center <?= $workermanStatus ? 'bg-emerald-500/10 text-emerald-500' : 'bg-red-500/10 text-red-500' ?>">
                    <i class="fas fa-bolt text-xl"></i>
                </div>
                <span class="text-[8px] font-black uppercase tracking-widest <?= $workermanStatus ? 'text-emerald-500' : 'text-red-500' ?>">
                    <?= $workermanStatus ? 'En ligne' : 'Hors ligne' ?>
                </span>
            </div>
            <h3 class="text-xs font-black uppercase text-slate-400 mb-2">Moteur Temps Réel</h3>
            <p class="text-xl font-black text-slate-900 dark:text-white uppercase">Workerman WebSocket</p>
            <?php if (!$workermanStatus): ?>
                <p class="mt-4 text-[9px] font-bold text-slate-500 leading-relaxed italic">Le serveur WebSocket n'est pas lancé. Les notifications temps réel ne fonctionneront pas.</p>
            <?php endif; ?>
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
            <p class="text-xl font-black text-slate-900 dark:text-white uppercase"><?= $memory ?></p>
            <p class="mt-4 text-[9px] font-bold text-slate-500 uppercase">Load: <?= $load ?></p>
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
</script>

<?php require_once __DIR__ . '/footer.php'; ?>

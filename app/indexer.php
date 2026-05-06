<?php
// ============================================================
// scripts/indexer.php — Script d'indexation CLI / Web
// Usage CLI : php indexer.php [--root=/path/to/archive]
// Usage Web : ouvrir indexer.php et saisir un dossier
// ============================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/scan.php';
require_once __DIR__ . '/scan_go.php';

set_time_limit(0);
ini_set('display_errors', '1');
error_reporting(E_ALL);

$db = getDB();
$history = getScanHistory($db, 20);
$defaultRoot = $history[0]['root_path'] ?? ARCHIVE_ROOT;
$isCli = php_sapi_name() === 'cli';

$requestedRoot = null;
if ($isCli) {
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--root=')) {
            $requestedRoot = substr($arg, 7);
            break;
        }
    }
} else {
    $requestedRoot = trim($_POST['root'] ?? $_GET['root'] ?? '');
}

$archiveRoot = $requestedRoot !== '' ? $requestedRoot : $defaultRoot;
$archiveRoot = realpath($archiveRoot) ?: $archiveRoot;

if ($isCli) {
    try {
        if ($requestedRoot !== null && $requestedRoot !== '') {
            $archiveRoot = validatePath($requestedRoot);
        } else {
            $archiveRoot = validatePath($archiveRoot);
        }
        echo scanArchive($db, $archiveRoot);
    } catch (Throwable $e) {
        fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
        exit(1);
    }
    exit;
}

$message = '';
$output = '';
$messageType = 'info';
$engine = 'php';
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($requestedRoot === '') {
            throw new RuntimeException('Veuillez saisir un dossier à scanner.');
        }
        $engine = strtolower(trim((string)($_POST['engine'] ?? 'php')));
        if ($engine !== 'go' && $engine !== 'php') $engine = 'php';
        $archiveRoot = validatePath($requestedRoot);
        if ($engine === 'go') {
            $output = scanArchiveGo($db, $archiveRoot);
            $message = 'Indexation (Go) terminée pour : ' . htmlspecialchars($archiveRoot);
        } else {
            $output = scanArchive($db, $archiveRoot);
            $message = 'Indexation (PHP) terminée pour : ' . htmlspecialchars($archiveRoot);
        }
        $messageType = 'success';
    }
} catch (Throwable $e) {
    $message = 'Erreur : ' . $e->getMessage();
    $messageType = 'error';
}

$pageTitle = 'Indexer - GED-MEF';
$currentPage = 'indexer';
$baseHref = '../';
require_once __DIR__ . '/header.php';
?>

<div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
    <div class="absolute -top-24 left-1/2 h-[520px] w-[520px] -translate-x-1/2 rounded-full bg-brand/10 blur-3xl"></div>
    <div class="absolute -bottom-24 right-[-120px] h-[420px] w-[420px] rounded-full bg-indigo-500/10 blur-3xl"></div>
    <div class="absolute -bottom-36 left-[-120px] h-[360px] w-[360px] rounded-full bg-sky-500/10 blur-3xl"></div>
</div>

<main class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
    <div class="rounded-[32px] border border-slate-800 bg-slate-900/70 p-6 shadow-xl shadow-slate-950/40">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-slate-500">Archive</p>
                <h2 class="mt-2 text-3xl font-semibold text-white">Indexer</h2>
                <p class="mt-2 text-sm text-slate-400">Relance un scan complet et met à jour la base.</p>
            </div>
            <div class="rounded-3xl border border-slate-800 bg-slate-950/60 px-4 py-3 text-sm text-slate-300">
                <div class="text-xs text-slate-500">Dossier par défaut</div>
                <div class="mt-1 max-w-[36ch] truncate font-mono"><?= htmlspecialchars($archiveRoot) ?></div>
            </div>
        </div>

        <?php if ($message !== ''): ?>
            <?php
            $msgClass = 'border-slate-800 bg-slate-950/60 text-slate-200';
            if ($messageType === 'success') $msgClass = 'border-emerald-800/70 bg-emerald-950/30 text-emerald-200';
            if ($messageType === 'error') $msgClass = 'border-red-800/70 bg-red-950/30 text-red-200';
            ?>
            <div class="mt-6 rounded-3xl border px-5 py-4 text-sm <?= $msgClass ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="post" class="mt-6 space-y-3">
            <label class="text-sm font-medium text-slate-300" for="root">Dossier à scanner</label>
            <input type="text" id="root" name="root" value="<?= htmlspecialchars($archiveRoot) ?>" placeholder="/chemin/vers/archives"
                   class="w-full rounded-3xl border border-slate-800 bg-slate-950/60 px-4 py-3 text-sm text-slate-100 placeholder:text-slate-500 outline-none transition focus:border-brand focus:ring-2 focus:ring-brand/30">
            <div class="mt-2 grid gap-3 sm:grid-cols-2">
                <label class="flex cursor-pointer items-center gap-3 rounded-3xl border border-slate-800 bg-slate-950/40 px-4 py-3 text-sm text-slate-200 transition hover:bg-slate-900/50">
                    <input type="radio" name="engine" value="php" class="h-4 w-4 accent-blue-500" <?= $engine !== 'go' ? 'checked' : '' ?>>
                    <div class="min-w-0">
                        <div class="font-semibold text-white">Scanner PHP (fallback)</div>
                        <div class="mt-0.5 text-xs text-slate-400">Aucun binaire requis, marche partout.</div>
                    </div>
                </label>
                <label class="flex cursor-pointer items-center gap-3 rounded-3xl border border-slate-800 bg-slate-950/40 px-4 py-3 text-sm text-slate-200 transition hover:bg-slate-900/50">
                    <input type="radio" name="engine" value="go" class="h-4 w-4 accent-blue-500" <?= $engine === 'go' ? 'checked' : '' ?>>
                    <div class="min-w-0">
                        <div class="font-semibold text-white">Scanner Go (rapide)</div>
                        <div class="mt-0.5 text-xs text-slate-400">Utilise `bin/scannerfs` pour scanner le disque.</div>
                    </div>
                </label>
            </div>
            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <a href="<?= $baseHref ?>index.php" class="rounded-3xl border border-slate-800 bg-slate-900/60 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">Retour</a>
                <button type="submit" class="rounded-3xl bg-brand px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-brand/20 transition hover:bg-brand/90">Lancer l'indexation</button>
            </div>
        </form>

        <?php if ($output !== ''): ?>
            <div class="mt-6 overflow-hidden rounded-[32px] border border-slate-800 bg-slate-950/60">
                <div class="border-b border-slate-800 px-5 py-3 text-sm font-semibold text-slate-200">Résultat</div>
                <pre class="max-h-[420px] overflow-auto p-5 text-xs leading-6 text-slate-200"><?= htmlspecialchars($output) ?></pre>
            </div>
        <?php endif; ?>
    </div>

    <section class="mt-6 rounded-[32px] border border-slate-800 bg-slate-900/70 p-6 shadow-xl shadow-slate-950/40">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-slate-500">Historique</p>
                <h3 class="mt-2 text-xl font-semibold text-white">Dossiers scannés</h3>
            </div>
        </div>

        <?php if (count($history) === 0): ?>
            <div class="mt-4 rounded-3xl border border-dashed border-slate-800 bg-slate-950/40 px-5 py-10 text-center text-sm text-slate-500">
                Aucun dossier scanné pour le moment.
            </div>
        <?php else: ?>
            <div class="mt-4 space-y-3">
                <?php foreach ($history as $item): ?>
                    <?php
                    $rootPath = (string)($item['root_path'] ?? '');
                    $createdAt = (string)($item['created_at'] ?? '');
                    ?>
                    <form method="post" class="flex flex-col gap-3 rounded-[28px] border border-slate-800 bg-slate-950/50 p-4 sm:flex-row sm:items-center sm:gap-4">
                        <input type="hidden" name="root" value="<?= htmlspecialchars($rootPath) ?>">
                        <button type="submit" class="inline-flex items-center justify-center rounded-3xl bg-slate-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700">
                            Scanner
                        </button>
                        <div class="min-w-0 flex-1">
                            <div class="truncate font-mono text-sm text-slate-200"><?= htmlspecialchars($rootPath) ?></div>
                            <div class="mt-1 text-xs text-slate-500"><?= htmlspecialchars($createdAt) ?></div>
                        </div>
                    </form>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php
require_once __DIR__ . '/footer.php';

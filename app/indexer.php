<?php
// app/indexer.php — Version Grise Stylisée

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/scan.php';
require_once __DIR__ . '/scan_go.php';

set_time_limit(0);
$db = getDB();
$history = getScanHistory($db, 20);
$defaultRoot = $history[0]['root_path'] ?? ARCHIVE_ROOT;

$requestedRoot = trim($_POST['root'] ?? $_GET['root'] ?? '');
$archiveRoot = $requestedRoot !== '' ? $requestedRoot : $defaultRoot;
$archiveRoot = realpath($archiveRoot) ?: $archiveRoot;

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
        $archiveRoot = validatePath($requestedRoot);
        if ($engine === 'go') {
            $output = scanArchiveGo($db, $archiveRoot);
            $message = 'Indexation terminée';
        } else {
            $output = scanArchive($db, $archiveRoot);
            $message = 'Indexation terminée';
        }
        $messageType = 'success';
        $history = getScanHistory($db, 20);
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

<main class="mx-auto max-w-5xl px-4 py-12 sm:px-6">
    <div class="mb-10 text-center">
        <h2 class="text-4xl font-black text-slate-900 tracking-tighter uppercase">Indexation</h2>
        <p class="mt-2 text-xs font-black uppercase tracking-[0.3em] text-indigo-600">Mise à jour du répertoire numérique</p>
    </div>

    <?php if ($message !== ''): ?>
        <div class="mb-8 rounded-2xl border p-5 text-xs font-black uppercase tracking-widest flex items-center gap-4 animate-in zoom-in duration-300
            <?= $messageType === 'success' ? 'border-emerald-500 bg-white text-emerald-600' : 'border-red-500 bg-white text-red-600' ?>">
            <i class="fas <?= $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> text-xl"></i>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="grid gap-10 lg:grid-cols-[1fr_300px]">
        <!-- Formulaire -->
        <section class="space-y-8">
            <div class="rounded-3xl border border-stylized-dark bg-stylized-light p-10 shadow-sm">
                <form method="post" class="space-y-10">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 mb-4" for="root">Emplacement de l'archive</label>
                        <div class="relative group">
                            <i class="fas fa-terminal absolute left-5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <input type="text" id="root" name="root" value="<?= htmlspecialchars($archiveRoot) ?>" 
                                   class="w-full rounded-2xl border-none bg-white py-5 pl-14 pr-6 text-sm font-bold text-slate-900 shadow-inner focus:ring-4 focus:ring-indigo-600/10 transition">
                        </div>
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2">
                        <label class="relative flex cursor-pointer flex-col rounded-2xl border-2 border-stylized-dark bg-white p-6 transition hover:border-indigo-600 has-[:checked]:border-indigo-600 has-[:checked]:shadow-md">
                            <input type="radio" name="engine" value="php" class="peer sr-only" <?= $engine !== 'go' ? 'checked' : '' ?>>
                            <span class="text-xs font-black uppercase tracking-tight text-slate-900">Moteur PHP</span>
                            <span class="mt-2 text-[9px] font-bold text-slate-400 uppercase tracking-widest leading-relaxed">Stable & Universel</span>
                            <div class="absolute right-4 top-4 text-indigo-600 opacity-0 peer-checked:opacity-100 transition">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </label>
                        
                        <label class="relative flex cursor-pointer flex-col rounded-2xl border-2 border-stylized-dark bg-white p-6 transition hover:border-indigo-600 has-[:checked]:border-indigo-600 has-[:checked]:shadow-md">
                            <input type="radio" name="engine" value="go" class="peer sr-only" <?= $engine === 'go' ? 'checked' : '' ?>>
                            <span class="text-xs font-black uppercase tracking-tight text-slate-900">Moteur Go</span>
                            <span class="mt-2 text-[9px] font-bold text-slate-400 uppercase tracking-widest leading-relaxed">Haute Performance</span>
                            <div class="absolute right-4 top-4 text-indigo-600 opacity-0 peer-checked:opacity-100 transition">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </label>
                    </div>

                    <div class="flex items-center justify-between gap-6 border-t border-stylized-dark pt-10">
                        <a href="<?= $baseHref ?>index.php" class="text-[10px] font-black uppercase tracking-widest text-slate-400 hover:text-slate-900 transition">
                            <i class="fas fa-arrow-left mr-2"></i> Annuler
                        </a>
                        <button type="submit" class="rounded-2xl bg-indigo-600 px-10 py-5 text-[11px] font-black uppercase tracking-widest text-white shadow-lg shadow-indigo-600/20 hover:bg-indigo-700 hover:scale-[1.02] transition">
                            Démarrer l'indexation
                        </button>
                    </div>
                </form>
            </div>

            <?php if ($output !== ''): ?>
                <div class="rounded-3xl bg-slate-800 p-8 shadow-2xl">
                    <div class="mb-6 flex items-center justify-between border-b border-slate-700 pb-4">
                        <span class="text-[9px] font-black uppercase tracking-[0.3em] text-slate-500">Flux de sortie</span>
                        <div class="flex gap-1">
                            <div class="h-2 w-2 rounded-full bg-red-500/50"></div>
                            <div class="h-2 w-2 rounded-full bg-amber-500/50"></div>
                            <div class="h-2 w-2 rounded-full bg-emerald-500/50"></div>
                        </div>
                    </div>
                    <pre class="max-h-[300px] overflow-auto text-[10px] font-mono leading-relaxed text-indigo-300"><?= htmlspecialchars($output) ?></pre>
                </div>
            <?php endif; ?>
        </section>

        <!-- Historique -->
        <aside class="space-y-6">
            <div class="rounded-3xl border border-stylized-dark bg-stylized-light p-8 shadow-sm">
                <h3 class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 mb-8 flex items-center gap-3">
                    <i class="fas fa-history text-slate-300"></i> Historique
                </h3>
                
                <?php if (count($history) === 0): ?>
                    <div class="text-center text-[9px] font-black uppercase text-slate-300 py-10 border-2 border-dashed border-stylized-dark rounded-2xl italic">Aucune donnée</div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($history as $item): ?>
                            <form method="post">
                                <input type="hidden" name="root" value="<?= htmlspecialchars($item['root_path']) ?>">
                                <button type="submit" class="w-full text-left rounded-2xl border border-transparent bg-white p-4 transition hover:border-indigo-600 hover:shadow-md group">
                                    <span class="block truncate text-[10px] font-black text-slate-900 group-hover:text-indigo-600 transition uppercase"><?= htmlspecialchars(basename($item['root_path'])) ?></span>
                                    <span class="mt-1 block text-[8px] font-bold text-slate-400 uppercase"><?= htmlspecialchars($item['created_at']) ?></span>
                                </button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</main>

<?php require_once __DIR__ . '/footer.php'; ?>

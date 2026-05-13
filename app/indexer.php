<?php
// app/indexer.php — Version Grise Stylisée

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/scan.php';
require_once __DIR__ . '/scan_go.php';

check_admin();
ensureSessionStarted();
set_time_limit(0);

$db = getDB();

// 1. Déterminer le chemin par défaut (Session > Histoire > Config)
if (!isset($_SESSION['archive_root'])) {
    $history = getScanHistory($db, 1);
    $_SESSION['archive_root'] = $history[0]['root_path'] ?? ARCHIVE_ROOT;
}

// 2. Gérer les requêtes (POST/GET)
$requestedRoot = trim($_POST['root'] ?? $_GET['root'] ?? '');
if ($requestedRoot !== '') {
    $_SESSION['archive_root'] = $requestedRoot;
}

$archiveRoot = $_SESSION['archive_root'];
$message = $_SESSION['index_message'] ?? '';
$messageType = $_SESSION['index_message_type'] ?? 'info';
$output = $_SESSION['index_output'] ?? '';
$engine = $_SESSION['index_engine'] ?? 'php';

// Nettoyer les messages flash après lecture
unset($_SESSION['index_message'], $_SESSION['index_message_type'], $_SESSION['index_output']);

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'scan') {
        if ($requestedRoot === '') {
            throw new RuntimeException('Veuillez saisir un dossier à scanner.');
        }
        
        $engine = strtolower(trim((string)($_POST['engine'] ?? 'php')));
        $_SESSION['index_engine'] = $engine;
        
        $validatedPath = validatePath($requestedRoot);
        // On met à jour la session avec le chemin validé (normalisé)
        $_SESSION['archive_root'] = $validatedPath;
        $archiveRoot = $validatedPath;

        if ($engine === 'go') {
            if (!isGoScannerAvailable()) {
                $output = scanArchive($db, $archiveRoot);
                $message = 'Scanner Go introuvable ou non exécutable ; fallback sur le moteur PHP.';
            } else {
                $output = scanArchiveGo($db, $archiveRoot);
                $message = 'Indexation terminée avec le moteur Go.';
            }
        } else {
            $output = scanArchive($db, $archiveRoot);
            $message = 'Indexation terminée avec le moteur PHP.';
        }
        
        $_SESSION['index_message'] = $message;
        $_SESSION['index_message_type'] = 'success';
        $_SESSION['index_output'] = $output;
        
        // Redirection pour éviter le re-scan au F5 (PRG pattern)
        header('Location: indexer.php');
        exit;
    }
} catch (Throwable $e) {
    $_SESSION['index_message'] = 'Erreur : ' . $e->getMessage();
    $_SESSION['index_message_type'] = 'error';
    header('Location: indexer.php');
    exit;
}

$history = getScanHistory($db, 20);
$pageTitle = 'Indexer - GED-MEF';
$currentPage = 'indexer';
$baseHref = '../';
require_once __DIR__ . '/header.php';
?>

<main class="mx-auto max-w-5xl px-4 py-12 sm:px-6">
    <div class="mb-10 text-center">
        <h2 class="text-4xl font-black text-slate-900 dark:text-white tracking-tighter uppercase">Indexation</h2>
        <p class="mt-2 text-xs font-black uppercase tracking-[0.3em] text-indigo-600 dark:text-indigo-400">Mise à jour du répertoire numérique</p>
    </div>

    <?php if ($message !== ''): ?>
        <div class="mb-8 rounded-2xl border p-5 text-xs font-black uppercase tracking-widest flex items-center gap-4 animate-in zoom-in duration-300
            <?= $messageType === 'success' ? 'border-emerald-500 bg-white dark:bg-slate-900 text-emerald-600' : 'border-red-500 bg-white dark:bg-slate-900 text-red-600' ?>">
            <i class="fas <?= $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> text-xl"></i>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="grid gap-10 lg:grid-cols-[1fr_300px]">
        <!-- Formulaire -->
        <section class="space-y-8">
            <div class="rounded-3xl border border-slate-200 dark:border-white/5 bg-white dark:bg-slate-900 p-10 shadow-sm">
                <form method="post" class="space-y-10">
                    <input type="hidden" name="action" value="scan">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 mb-4" for="root">Emplacement de l'archive</label>
                        <div class="relative group">
                            <i class="fas fa-terminal absolute left-5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <input type="text" id="root" name="root" value="<?= htmlspecialchars($archiveRoot) ?>" 
                                   class="w-full rounded-2xl border-none bg-slate-50 dark:bg-slate-950 py-5 pl-14 pr-14 text-sm font-bold text-slate-900 dark:text-white shadow-inner focus:ring-4 focus:ring-indigo-600/10 transition">
                            <button type="button" id="btnBrowseRoot"
                                    class="absolute right-4 top-1/2 -translate-y-1/2 h-10 w-10 rounded-xl bg-white/70 dark:bg-slate-900/70 border border-slate-200 dark:border-white/5 text-slate-500 hover:text-indigo-600 hover:border-indigo-300 dark:hover:border-indigo-600 transition shadow-sm"
                                    title="Parcourir...">
                                <i class="fas fa-folder-open"></i>
                            </button>
                        </div>
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2">
                        <label class="relative flex cursor-pointer flex-col rounded-2xl border-2 border-slate-200 dark:border-white/5 bg-slate-50 dark:bg-slate-950 p-6 transition hover:border-indigo-600 has-[:checked]:border-indigo-600 has-[:checked]:shadow-md">
                            <input type="radio" name="engine" value="php" class="peer sr-only" <?= $engine !== 'go' ? 'checked' : '' ?>>
                            <span class="text-xs font-black uppercase tracking-tight text-slate-900 dark:text-white">Moteur PHP</span>
                            <span class="mt-2 text-[9px] font-bold text-slate-400 uppercase tracking-widest leading-relaxed">Stable & Universel</span>
                            <div class="absolute right-4 top-4 text-indigo-600 opacity-0 peer-checked:opacity-100 transition">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </label>
                        
                        <label class="relative flex cursor-pointer flex-col rounded-2xl border-2 border-slate-200 dark:border-white/5 bg-slate-50 dark:bg-slate-950 p-6 transition hover:border-indigo-600 has-[:checked]:border-indigo-600 has-[:checked]:shadow-md">
                            <input type="radio" name="engine" value="go" class="peer sr-only" <?= $engine === 'go' ? 'checked' : '' ?>>
                            <span class="text-xs font-black uppercase tracking-tight text-slate-900 dark:text-white">Moteur Go</span>
                            <span class="mt-2 text-[9px] font-bold text-slate-400 uppercase tracking-widest leading-relaxed">Haute Performance</span>
                            <div class="absolute right-4 top-4 text-indigo-600 opacity-0 peer-checked:opacity-100 transition">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </label>
                    </div>

                    <div class="flex items-center justify-between gap-6 border-t border-slate-100 dark:border-white/5 pt-10">
                        <a href="<?= $baseHref ?>index.php" class="text-[10px] font-black uppercase tracking-widest text-slate-400 hover:text-slate-900 dark:hover:text-white transition">
                            <i class="fas fa-arrow-left mr-2"></i> Annuler
                        </a>
                        <button type="submit" class="rounded-2xl bg-indigo-600 px-10 py-5 text-[11px] font-black uppercase tracking-widest text-white shadow-lg shadow-indigo-600/20 hover:bg-indigo-700 hover:scale-[1.02] transition">
                            Démarrer l'indexation
                        </button>
                    </div>
                </form>
            </div>

            <?php if ($output !== ''): ?>
                <div class="rounded-3xl bg-slate-900 p-8 shadow-2xl border border-white/5">
                    <div class="mb-6 flex items-center justify-between border-b border-slate-800 pb-4">
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
            <div class="rounded-3xl border border-slate-200 dark:border-white/5 bg-white dark:bg-slate-900 p-8 shadow-sm">
                <h3 class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 mb-8 flex items-center gap-3">
                    <i class="fas fa-history text-slate-300"></i> Historique
                </h3>
                
                <?php if (count($history) === 0): ?>
                    <div class="text-center text-[9px] font-black uppercase text-slate-300 py-10 border-2 border-dashed border-slate-100 dark:border-white/5 rounded-2xl italic">Aucune donnée</div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($history as $item): ?>
                            <form method="post">
                                <input type="hidden" name="action" value="scan">
                                <input type="hidden" name="root" value="<?= htmlspecialchars($item['root_path']) ?>">
                                <button type="submit" class="w-full text-left rounded-2xl border border-transparent bg-slate-50 dark:bg-slate-950 p-4 transition hover:border-indigo-600 hover:shadow-md group">
                                    <span class="block truncate text-[10px] font-black text-slate-900 dark:text-white group-hover:text-indigo-600 transition uppercase"><?= htmlspecialchars(basename($item['root_path'])) ?></span>
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

<!-- Modal : Explorateur de dossiers -->
<div id="dirPickerModal" class="fixed inset-0 z-[120] hidden items-center justify-center p-4">
    <div id="dirPickerOverlay" class="absolute inset-0 bg-slate-950/70 backdrop-blur-sm"></div>
    <div class="relative w-full max-w-3xl rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/5 shadow-2xl overflow-hidden">
        <div class="flex items-center justify-between px-8 py-6 border-b border-slate-100 dark:border-white/5">
            <div class="flex items-center gap-4">
                <div class="h-12 w-12 rounded-2xl bg-indigo-600 text-white flex items-center justify-center shadow-lg shadow-indigo-600/20">
                    <i class="fas fa-folder-tree text-xl"></i>
                </div>
                <div>
                    <div class="text-[11px] font-black uppercase tracking-widest text-slate-900 dark:text-white">Choisir un dossier</div>
                    <div id="dirPickerCurrent" class="mt-1 text-[10px] font-bold text-slate-400 dark:text-slate-500 break-all"></div>
                </div>
            </div>
            <button type="button" id="dirPickerClose" class="h-12 w-12 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-white/5 text-slate-500 hover:text-red-500 transition">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="px-8 py-6 flex items-center justify-between gap-4 border-b border-slate-100 dark:border-white/5">
            <div class="flex items-center gap-3">
                <button type="button" id="dirPickerUp"
                        class="h-11 px-5 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-white/5 text-[10px] font-black uppercase tracking-widest text-slate-600 dark:text-slate-300 hover:border-indigo-300 dark:hover:border-indigo-600 transition disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-level-up-alt mr-2"></i> Parent
                </button>
                <button type="button" id="dirPickerRoots"
                        class="h-11 px-5 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-white/5 text-[10px] font-black uppercase tracking-widest text-slate-600 dark:text-slate-300 hover:border-indigo-300 dark:hover:border-indigo-600 transition">
                    <i class="fas fa-hard-drive mr-2"></i> Racines
                </button>
            </div>

            <button type="button" id="dirPickerSelect"
                    class="h-11 px-6 rounded-2xl bg-indigo-600 text-white text-[10px] font-black uppercase tracking-widest shadow-lg shadow-indigo-600/20 hover:bg-indigo-700 transition">
                <i class="fas fa-check mr-2"></i> Sélectionner
            </button>
        </div>

        <div class="max-h-[55vh] overflow-auto px-4 py-4">
            <div id="dirPickerList" class="grid gap-2"></div>
        </div>
    </div>
</div>

<script>
(() => {
    const modal = document.getElementById('dirPickerModal');
    const overlay = document.getElementById('dirPickerOverlay');
    const btnClose = document.getElementById('dirPickerClose');
    const btnBrowse = document.getElementById('btnBrowseRoot');
    const btnUp = document.getElementById('dirPickerUp');
    const btnRoots = document.getElementById('dirPickerRoots');
    const btnSelect = document.getElementById('dirPickerSelect');
    const currentEl = document.getElementById('dirPickerCurrent');
    const listEl = document.getElementById('dirPickerList');
    const rootInput = document.getElementById('root');

    let currentPath = '';
    let parentPath = null;

    const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({
        '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
    }[c]));

    function openModal() {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        loadPath(rootInput.value.trim() || '');
    }

    function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    async function api(url) {
        const res = await fetch(url, { cache: 'no-store' });
        const data = await res.json().catch(() => null);
        if (!res.ok || !data || data.ok === false) {
            const msg = data && data.message ? data.message : `HTTP ${res.status}`;
            throw new Error(msg);
        }
        return data;
    }

    function setLoading() {
        listEl.innerHTML = `
            <div class="py-16 text-center">
                <div class="inline-flex h-12 w-12 items-center justify-center rounded-full border-4 border-indigo-600/20 border-t-indigo-600 animate-spin mb-4"></div>
                <div class="text-[10px] font-black uppercase tracking-widest text-slate-400">Chargement...</div>
            </div>
        `;
    }

    function renderDirs(dirs) {
        if (!Array.isArray(dirs) || dirs.length === 0) {
            listEl.innerHTML = `
                <div class="py-12 text-center text-[10px] font-black uppercase tracking-widest text-slate-400">
                    Aucun sous-dossier
                </div>
            `;
            return;
        }

        listEl.innerHTML = dirs.map(d => `
            <button type="button"
                    class="dir-item flex items-center justify-between gap-4 rounded-2xl border border-slate-200 dark:border-white/5 bg-slate-50 dark:bg-slate-950 px-5 py-4 text-left hover:border-indigo-300 dark:hover:border-indigo-600 hover:shadow-sm transition"
                    data-path="${esc(d.path)}">
                <div class="flex items-center gap-4 overflow-hidden">
                    <div class="h-10 w-10 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/5 flex items-center justify-center text-indigo-600">
                        <i class="fas fa-folder"></i>
                    </div>
                    <div class="truncate">
                        <div class="text-[11px] font-black uppercase tracking-tight text-slate-900 dark:text-white truncate">${esc(d.name)}</div>
                        <div class="mt-1 text-[9px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500 truncate">${esc(d.path)}</div>
                    </div>
                </div>
                <i class="fas fa-chevron-right text-slate-300 dark:text-slate-600"></i>
            </button>
        `).join('');

        listEl.querySelectorAll('.dir-item').forEach(btn => {
            btn.addEventListener('click', () => loadPath(btn.dataset.path || ''));
        });
    }

    async function loadRoots() {
        setLoading();
        const data = await api('browse_dirs.php?action=roots');
        currentPath = '';
        parentPath = null;
        currentEl.textContent = 'Racines';
        btnUp.disabled = true;
        btnSelect.disabled = true;

        listEl.innerHTML = (data.roots || []).map(r => `
            <button type="button"
                    class="dir-root flex items-center justify-between gap-4 rounded-2xl border border-slate-200 dark:border-white/5 bg-white dark:bg-slate-950 px-5 py-4 text-left hover:border-indigo-300 dark:hover:border-indigo-600 hover:shadow-sm transition"
                    data-path="${esc(r.path)}">
                <div class="flex items-center gap-4 overflow-hidden">
                    <div class="h-10 w-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center">
                        <i class="fas fa-hdd"></i>
                    </div>
                    <div class="truncate">
                        <div class="text-[11px] font-black uppercase tracking-tight text-slate-900 dark:text-white truncate">${esc(r.name)}</div>
                        <div class="mt-1 text-[9px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500 truncate">${esc(r.path)}</div>
                    </div>
                </div>
                <i class="fas fa-chevron-right text-slate-300 dark:text-slate-600"></i>
            </button>
        `).join('');

        listEl.querySelectorAll('.dir-root').forEach(btn => {
            btn.addEventListener('click', () => loadPath(btn.dataset.path || ''));
        });
    }

    async function loadPath(path) {
        const p = (path || '').trim();
        if (p === '') {
            await loadRoots();
            return;
        }
        setLoading();
        const data = await api(`browse_dirs.php?path=${encodeURIComponent(p)}`);
        currentPath = data.current || '';
        parentPath = data.parent ?? null;
        currentEl.textContent = currentPath;
        btnUp.disabled = !parentPath;
        btnSelect.disabled = !currentPath;
        renderDirs(data.dirs || []);
    }

    btnBrowse?.addEventListener('click', openModal);
    btnClose?.addEventListener('click', closeModal);
    overlay?.addEventListener('click', closeModal);
    btnRoots?.addEventListener('click', () => loadRoots());
    btnUp?.addEventListener('click', () => parentPath ? loadPath(parentPath) : loadRoots());
    btnSelect?.addEventListener('click', () => {
        if (currentPath) rootInput.value = currentPath;
        closeModal();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
    });
})();
</script>

<?php require_once __DIR__ . '/footer.php'; ?>

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
    // Suppression d'un élément de l'historique
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_history') {
        $historyId = (int)($_POST['history_id'] ?? 0);
        if ($historyId > 0) {
            deleteHistoryItem($db, $historyId);
            $message = "Élément supprimé de l'historique.";
            $messageType = 'success';
        }
    }

    // Suppression de TOUT l'historique
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_all_history') {
        clearAllHistory($db);
        $message = "Tout l'historique a été effacé.";
        $messageType = 'success';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'scan') {
        if ($requestedRoot === '') {
            throw new RuntimeException('Veuillez saisir un dossier à scanner.');
        }
        
        $validatedPath = validatePath($requestedRoot);
        // On met à jour la session avec le chemin validé (normalisé)
        $_SESSION['archive_root'] = $validatedPath;
        $archiveRoot = $validatedPath;

        // Stratégie Automatique : Priorité à GO, Fallback sur PHP
        if (isGoScannerAvailable()) {
            try {
                $output = scanArchiveGo($db, $archiveRoot);
                $message = 'Indexation terminée avec succès (Moteur Haute Performance).';
                $engine = 'go';
            } catch (Throwable $goErr) {
                // Si Go échoue pour une raison quelconque, on tente PHP
                $output = scanArchive($db, $archiveRoot);
                $message = 'Indexation terminée avec succès (Moteur Standard - Fallback).';
                $engine = 'php';
            }
        } else {
            // Go non disponible sur ce système
            $output = scanArchive($db, $archiveRoot);
            $message = 'Indexation terminée avec succès (Moteur Standard).';
            $engine = 'php';
        }
        
        $_SESSION['index_engine'] = $engine;
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

                    <div class="rounded-2xl border-2 border-indigo-600/20 bg-indigo-50/30 dark:bg-indigo-900/10 p-6">
                        <div class="flex items-center gap-4">
                            <div class="h-10 w-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center shadow-lg shadow-indigo-600/20">
                                <i class="fas fa-microchip text-lg"></i>
                            </div>
                            <div>
                                <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-tight">Moteur Hybride Intelligent</h3>
                                <p class="text-[9px] font-bold text-slate-500 uppercase tracking-widest mt-0.5">Priorité Go avec Fallback PHP automatique</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-6 border-t border-slate-100 dark:border-white/5 pt-10">
                        <a href="<?= $baseHref ?>index.php" class="text-[10px] font-black uppercase tracking-widest text-slate-400 hover:text-slate-900 dark:hover:text-white transition">
                            <i class="fas fa-arrow-left mr-2"></i> Annuler
                        </a>
                        <button type="submit" class="rounded-2xl bg-indigo-600 px-10 py-5 text-[11px] font-black uppercase tracking-widest text-white shadow-lg shadow-indigo-600/20 hover:bg-indigo-700 hover:scale-[1.02] transition">
                            Démarrer l'indexation intelligente
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
                <div class="mb-8 flex items-center justify-between gap-4">
                    <h3 class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 flex items-center gap-3">
                        <i class="fas fa-history text-slate-300"></i> Historique
                    </h3>
                    <?php if (count($history) > 0): ?>
                        <form method="post" onsubmit="event.preventDefault(); confirmAction('Voulez-vous vraiment effacer TOUT l\'historique ?', () => this.submit());">
                            <input type="hidden" name="action" value="clear_all_history">
                            <button type="submit" class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-500 hover:bg-red-500 hover:text-white transition group">
                                <span class="text-[8px] font-black uppercase tracking-widest">Effacer Tout</span>
                                <i class="fas fa-trash-alt text-[9px]"></i>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
                
                <?php if (count($history) === 0): ?>
                    <div class="text-center text-[9px] font-black uppercase text-slate-300 py-10 border-2 border-dashed border-slate-100 dark:border-white/5 rounded-2xl italic">Aucune donnée</div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($history as $item): ?>
                            <div class="group relative">
                                <form method="post">
                                    <input type="hidden" name="action" value="scan">
                                    <input type="hidden" name="root" value="<?= htmlspecialchars($item['root_path']) ?>">
                                    <button type="submit" class="w-full text-left rounded-2xl border border-transparent bg-slate-50 dark:bg-slate-950 p-4 pr-12 transition hover:border-indigo-600 hover:shadow-md">
                                        <span class="block truncate text-[10px] font-black text-slate-900 dark:text-white group-hover:text-indigo-600 transition uppercase"><?= htmlspecialchars(basename($item['root_path'])) ?></span>
                                        <span class="mt-1 block text-[8px] font-bold text-slate-400 uppercase"><?= htmlspecialchars($item['created_at']) ?></span>
                                    </button>
                                </form>
                                <form method="post" onsubmit="event.preventDefault(); confirmAction('Supprimer cet élément de l\'historique ?', () => this.submit());" class="absolute right-3 top-1/2 -translate-y-1/2 opacity-0 group-hover:opacity-100 transition">
                                    <input type="hidden" name="action" value="delete_history">
                                    <input type="hidden" name="history_id" value="<?= (int)$item['id'] ?>">
                                    <button type="submit" class="h-8 w-8 rounded-xl bg-red-50 dark:bg-red-950/30 text-red-500 hover:bg-red-500 hover:text-white flex items-center justify-center transition shadow-sm">
                                        <i class="fas fa-trash text-[10px]"></i>
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</main>

<style>
    /* Fallback: si <dialog> n'est pas supporté, éviter d'afficher le contenu en permanence */
    dialog:not([open]) { display: none; }
    dialog::backdrop { background: rgba(2, 6, 23, 0.7); backdrop-filter: blur(6px); }
</style>

<!-- Explorateur de dossiers COPIE CONFORME Windows -->
<dialog id="dirPickerModal" class="z-[120] w-[95%] max-w-5xl h-[85vh] rounded-none bg-white dark:bg-[#191919] border border-[#0078d7] shadow-2xl overflow-hidden p-0 font-['Segoe_UI',_Tahoma,_Arial,_sans-serif]">
    <!-- Barre de titre Windows 10/11 Style -->
    <div class="flex items-center justify-between px-3 py-1 bg-white dark:bg-[#191919] select-none">
        <div class="flex items-center gap-2">
            <img src="https://win98icons.alexmeub.com/icons/png/directory_closed-4.png" class="w-4 h-4" alt="">
            <div class="w-[1px] h-4 bg-slate-300 mx-1"></div>
            <span class="text-[12px] text-[#333] dark:text-[#eee]">Choisir un dossier</span>
        </div>
        <div class="flex">
            <button type="button" class="w-11 h-8 flex items-center justify-center hover:bg-[#e5e5e5] dark:hover:bg-[#333] transition-colors">
                <i class="fas fa-minus text-[10px] text-[#333] dark:text-[#eee]"></i>
            </button>
            <button type="button" class="w-11 h-8 flex items-center justify-center hover:bg-[#e5e5e5] dark:hover:bg-[#333] transition-colors">
                <i class="far fa-square text-[10px] text-[#333] dark:text-[#eee]"></i>
            </button>
            <button type="button" id="dirPickerClose" class="w-11 h-8 flex items-center justify-center hover:bg-[#e81123] hover:text-white transition-colors group">
                <i class="fas fa-times text-[12px] text-[#333] dark:text-[#eee] group-hover:text-white"></i>
            </button>
        </div>
    </div>

    <!-- Barre de navigation (Précédent/Suivant/Haut/Adresse) -->
    <div class="px-2 py-1.5 bg-white dark:bg-[#191919] flex items-center gap-2 border-b border-[#f0f0f0] dark:border-[#2d2d2d]">
        <div class="flex items-center gap-1">
            <button type="button" id="dirPickerBack" class="p-1.5 text-[#333] dark:text-[#ccc] hover:bg-[#e5f1fb] dark:hover:bg-[#333] rounded disabled:opacity-30 disabled:hover:bg-transparent transition-colors">
                <i class="fas fa-arrow-left text-[14px]"></i>
            </button>
            <button type="button" id="dirPickerForward" class="p-1.5 text-[#333] dark:text-[#ccc] hover:bg-[#e5f1fb] dark:hover:bg-[#333] rounded disabled:opacity-30 disabled:hover:bg-transparent transition-colors">
                <i class="fas fa-arrow-right text-[14px]"></i>
            </button>
            <button type="button" class="p-1.5 text-[#333] dark:text-[#ccc] hover:bg-[#e5f1fb] dark:hover:bg-[#333] rounded">
                <i class="fas fa-chevron-down text-[10px]"></i>
            </button>
            <button type="button" id="dirPickerUp" class="p-1.5 text-[#333] dark:text-[#ccc] hover:bg-[#e5f1fb] dark:hover:bg-[#333] rounded disabled:opacity-30">
                <i class="fas fa-arrow-up text-[14px]"></i>
            </button>
        </div>
        
        <!-- Barre d'adresse Windows -->
        <div class="flex-1 flex items-center h-[30px] bg-white dark:bg-[#252525] border border-[#d9d9d9] dark:border-[#333] px-2 group focus-within:border-[#0078d7]">
            <i class="fas fa-desktop text-[#0078d7] text-[12px] mr-2"></i>
            <div class="flex-1 flex items-center overflow-hidden text-[12px] text-[#333] dark:text-[#ccc]">
                <span id="dirPickerCurrent" class="truncate flex items-center">
                    <span class="hover:bg-[#e5f1fb] dark:hover:bg-[#333] px-1 cursor-pointer">Ce PC</span>
                </span>
            </div>
            <i id="dirPickerRefresh" class="fas fa-sync-alt text-[#999] text-[10px] ml-2 cursor-pointer hover:text-[#333] dark:hover:text-[#eee]"></i>
        </div>

        <!-- Barre de recherche -->
        <div class="w-64 h-[30px] relative bg-white dark:bg-[#252525] border border-[#d9d9d9] dark:border-[#333] px-2 flex items-center">
            <input type="text" id="dirPickerSearch" placeholder="Rechercher" class="w-full bg-transparent text-[12px] outline-none text-[#333] dark:text-[#ccc]">
            <i class="fas fa-search text-[#999] text-[12px]"></i>
        </div>
    </div>

    <div class="flex h-[calc(85vh-125px)]">
        <!-- Volet latéral Windows (Arborescence) -->
        <div class="w-[200px] bg-white dark:bg-[#191919] border-r border-[#f0f0f0] dark:border-[#2d2d2d] overflow-y-auto select-none pt-2">
            <div id="side-quick-access" class="flex items-center gap-2 px-3 py-1 hover:bg-[#e5f1fb] dark:hover:bg-[#333] text-[12px] text-[#333] dark:text-[#ccc] cursor-pointer">
                <i class="fas fa-chevron-down text-[8px] text-[#999]"></i>
                <i class="fas fa-star text-[#0078d7] text-[12px]"></i>
                <span class="font-semibold">Accès rapide</span>
            </div>
            <div id="sidebar-quick-access" class="pl-8 space-y-0.5 mt-1 mb-2">
                <!-- Rempli dynamiquement -->
            </div>

            <div id="side-this-pc-root" class="flex items-center gap-2 px-3 py-1 hover:bg-[#e5f1fb] dark:hover:bg-[#333] text-[12px] text-[#333] dark:text-[#ccc] cursor-pointer bg-[#e5f1fb] dark:bg-[#333]">
                <i class="fas fa-chevron-down text-[8px] text-[#999]"></i>
                <i class="fas fa-desktop text-[#0078d7] text-[12px]"></i>
                <span class="font-semibold">Ce PC</span>
            </div>
            <div id="sidebar-drives" class="pl-8 space-y-0.5 mt-1">
                <!-- Rempli dynamiquement -->
                <div class="py-2 text-[10px] text-slate-400 italic">Chargement...</div>
            </div>
        </div>

        <!-- Zone de contenu (Tableau) -->
        <div class="flex-1 bg-white dark:bg-[#191919] overflow-y-auto">
            <table class="w-full text-left border-collapse">
                <thead class="sticky top-0 bg-white dark:bg-[#191919] select-none z-20">
                    <tr class="text-[12px] text-[#333] dark:text-[#999]">
                        <th class="font-normal px-3 py-1 border-r border-[#f0f0f0] dark:border-[#2d2d2d] hover:bg-[#e5f1fb] dark:hover:bg-[#333]">Nom</th>
                        <th class="font-normal px-3 py-1 border-r border-[#f0f0f0] dark:border-[#2d2d2d] hover:bg-[#e5f1fb] dark:hover:bg-[#333]">Modifié le</th>
                        <th class="font-normal px-3 py-1 hover:bg-[#e5f1fb] dark:hover:bg-[#333]">Type</th>
                    </tr>
                </thead>
                <tbody id="dirPickerList" class="text-[12px] select-none">
                    <!-- Rempli par JS -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- Barre de pied de page (Nom du dossier / Boutons) -->
    <div class="absolute bottom-0 left-0 right-0 bg-[#f0f0f0] dark:bg-[#191919] border-t border-[#d9d9d9] dark:border-[#2d2d2d] px-4 py-4 space-y-3">
        <div class="flex items-center gap-4">
            <span class="text-[12px] text-[#333] dark:text-[#ccc] min-w-[100px]">Nom du dossier :</span>
            <div class="flex-1 h-6 bg-white dark:bg-[#252525] border border-[#0078d7] px-2 flex items-center">
                <input type="text" id="dirPickerSelectedName" readonly class="w-full bg-transparent outline-none text-[12px] text-[#333] dark:text-[#ccc]">
            </div>
        </div>
        <div class="flex justify-end gap-2">
            <button type="button" id="dirPickerSelect" class="min-w-[100px] h-8 bg-[#e1e1e1] dark:bg-[#333] border border-[#adadad] dark:border-[#444] text-[12px] text-black dark:text-white hover:bg-[#cce4f7] hover:border-[#0078d7] transition-all outline-none focus:border-2">Sélectionner</button>
            <button type="button" onclick="document.getElementById('dirPickerModal').close()" class="min-w-[100px] h-8 bg-[#e1e1e1] dark:bg-[#333] border border-[#adadad] dark:border-[#444] text-[12px] text-black dark:text-white hover:bg-[#cce4f7] hover:border-[#0078d7] transition-all outline-none focus:border-2">Annuler</button>
        </div>
    </div>
</dialog>

<script>
(() => {
    const modal = document.getElementById('dirPickerModal');
    const btnClose = document.getElementById('dirPickerClose');
    const btnBrowse = document.getElementById('btnBrowseRoot');
    const btnBack = document.getElementById('dirPickerBack');
    const btnForward = document.getElementById('dirPickerForward');
    const btnUp = document.getElementById('dirPickerUp');
    const btnSelect = document.getElementById('dirPickerSelect');
    const currentEl = document.getElementById('dirPickerCurrent');
    const listEl = document.getElementById('dirPickerList');
    const sidebarDrives = document.getElementById('sidebar-drives');
    const sidebarQuickAccess = document.getElementById('sidebar-quick-access');
    const rootInput = document.getElementById('root');
    const selectedInput = document.getElementById('dirPickerSelectedName');
    const btnThisPc = document.getElementById('side-this-pc-root');
    const btnRefresh = document.getElementById('dirPickerRefresh');
    const searchInput = document.getElementById('dirPickerSearch');

    let currentPath = '';
    let parentPath = null;
    let currentDirs = []; 
    
    // Historique
    let backStack = [];
    let forwardStack = [];
    let isNavigatingHistory = false;

    const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({
        '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
    }[c]));

    function updateNavButtons() {
        if (btnBack) btnBack.disabled = backStack.length === 0;
        if (btnForward) btnForward.disabled = forwardStack.length === 0;
    }

    function addToHistory(path) {
        if (isNavigatingHistory) return;
        if (currentPath !== null && currentPath !== path) {
            backStack.push(currentPath);
            forwardStack = [];
        }
        updateNavButtons();
    }

    async function goBack() {
        if (backStack.length === 0) return;
        isNavigatingHistory = true;
        const target = backStack.pop();
        forwardStack.push(currentPath);
        if (target === '') await window.loadRoots();
        else await window.loadPath(target);
        isNavigatingHistory = false;
        updateNavButtons();
    }

    async function goForward() {
        if (forwardStack.length === 0) return;
        isNavigatingHistory = true;
        const target = forwardStack.pop();
        backStack.push(currentPath);
        if (target === '') await window.loadRoots();
        else await window.loadPath(target);
        isNavigatingHistory = false;
        updateNavButtons();
    }

    function updateAddressBar(path) {
        if (!currentEl) return;
        if (!path || path === '') {
            currentEl.innerHTML = '<span class="hover:bg-[#e5f1fb] dark:hover:bg-[#333] px-1 cursor-pointer" onclick="window.loadRoots()">Ce PC</span>';
            return;
        }
        const parts = path.split(/[\\\/]/).filter(Boolean);
        let html = '<span class="hover:bg-[#e5f1fb] dark:hover:bg-[#333] px-1 cursor-pointer" onclick="window.loadRoots()">Ce PC</span>';
        let cumulative = '';
        
        if (path.match(/^[a-zA-Z]:/)) {
            const drive = path.substring(0, 2);
            html += `<i class="fas fa-chevron-right text-[8px] mx-1 text-[#999]"></i><span class="hover:bg-[#e5f1fb] dark:hover:bg-[#333] px-1 cursor-pointer" onclick="window.loadPath('${drive}\\\\')">${drive}</span>`;
            cumulative = drive + '\\';
            parts.shift();
        }

        parts.forEach((p, i) => {
            cumulative += (cumulative.endsWith('\\') || cumulative.endsWith('/') ? '' : '/') + p;
            const pathForJs = cumulative.replace(/\\/g, '\\\\');
            html += `<i class="fas fa-chevron-right text-[8px] mx-1 text-[#999]"></i><span class="hover:bg-[#e5f1fb] dark:hover:bg-[#333] px-1 cursor-pointer" onclick="window.loadPath('${pathForJs}')">${esc(p)}</span>`;
        });
        currentEl.innerHTML = html;
    }

    async function api(url) {
        const res = await fetch(url, { cache: 'no-store' });
        const data = await res.json().catch(() => null);
        if (!res.ok || !data || data.ok === false) {
            throw new Error(data?.message || `HTTP ${res.status}`);
        }
        return data;
    }

    function setLoading() {
        listEl.innerHTML = `<tr><td colspan="3" class="py-12 text-center text-[12px] text-[#999] italic"><i class="fas fa-circle-notch animate-spin mr-2"></i> Chargement...</td></tr>`;
    }

    function renderDirs(dirs) {
        const isRoots = currentPath === '';
        if (!Array.isArray(dirs) || dirs.length === 0) {
            listEl.innerHTML = `<tr><td colspan="3" class="py-12 text-center text-[12px] text-[#999] italic">Ce dossier est vide</td></tr>`;
            return;
        }

        listEl.innerHTML = dirs.map(d => {
            const isDrive = isRoots && d.path.includes(':');
            const icon = isDrive ? 'hard_disk_drive-4.png' : 'directory_closed-4.png';
            const typeLabel = isRoots ? 'Disque local' : 'Dossier de fichiers';
            const dateLabel = d.date || (isRoots ? '--' : '--/--/---- --:--');
            const rowClass = isRoots ? 'dir-root' : 'dir-item';

            return `
                <tr class="${rowClass} group hover:bg-[#e5f1fb] dark:hover:bg-[#252525] cursor-pointer border-b border-transparent" data-path="${esc(d.path)}" data-name="${esc(d.name)}">
                    <td class="px-3 py-0.5 flex items-center gap-2 overflow-hidden">
                        <img src="https://win98icons.alexmeub.com/icons/png/${icon}" class="w-4 h-4 shrink-0" alt="">
                        <span class="truncate text-[#333] dark:text-[#eee]">${esc(d.name)}</span>
                    </td>
                    <td class="px-3 py-0.5 text-[#666] dark:text-[#888] whitespace-nowrap">${dateLabel}</td>
                    <td class="px-3 py-0.5 text-[#666] dark:text-[#888]">${typeLabel}</td>
                </tr>
            `;
        }).join('');

        listEl.querySelectorAll('.dir-item, .dir-root').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.dir-item, .dir-root').forEach(r => r.classList.remove('bg-[#cce8ff]', 'dark:bg-[#004d99]/30'));
                btn.classList.add('bg-[#cce8ff]', 'dark:bg-[#004d99]/30');
                if (selectedInput) selectedInput.value = btn.dataset.name;
                currentPath = btn.dataset.path;
            });
            btn.addEventListener('dblclick', () => window.loadPath(btn.dataset.path));
        });
    }

    // Gestion du tri
    let sortKey = 'name';
    let sortOrder = 1; // 1: asc, -1: desc

    function sortAndRender() {
        const sorted = [...currentDirs].sort((a, b) => {
            let valA = a[sortKey] || '';
            let valB = b[sortKey] || '';
            
            if (sortKey === 'date' && a.mtime && b.mtime) {
                valA = a.mtime;
                valB = b.mtime;
            }

            if (typeof valA === 'string') {
                return valA.localeCompare(valB, undefined, {numeric: true, sensitivity: 'base'}) * sortOrder;
            }
            return (valA - valB) * sortOrder;
        });

        // Update headers visual
        document.querySelectorAll('#dirPickerModal th').forEach(th => {
            const text = th.textContent.replace(/[▴▾]/g, '').trim();
            th.innerHTML = text;
            let thKey = 'name';
            if (text === 'Modifié le') thKey = 'date';
            if (thKey === sortKey) {
                th.innerHTML = text + (sortOrder === 1 ? ' <span class="text-[10px]">▴</span>' : ' <span class="text-[10px]">▾</span>');
            }
        });

        renderDirs(sorted);
    }

    document.querySelectorAll('#dirPickerModal th').forEach(th => {
        th.style.cursor = 'pointer';
        th.addEventListener('click', () => {
            const text = th.textContent.replace(/[▴▾]/g, '').trim();
            let newKey = 'name';
            if (text === 'Modifié le') newKey = 'date';
            if (text === 'Type') return; 

            if (sortKey === newKey) {
                sortOrder *= -1;
            } else {
                sortKey = newKey;
                sortOrder = 1;
            }
            sortAndRender();
        });
    });

    window.loadRoots = async function() {
        setLoading();
        if (searchInput) searchInput.value = '';
        try {
            const data = await api('browse_dirs.php?action=roots');
            addToHistory('');
            currentPath = '';
            parentPath = null;
            updateAddressBar('');
            updateNavButtons();
            if (selectedInput) selectedInput.value = '';
            btnUp.disabled = true;
            const roots = data.roots || [];
            currentDirs = roots.map(r => ({ 
                name: r.name, 
                path: r.path, 
                special: r.special,
                date: r.date,
                mtime: r.mtime
            }));
            const specialDirs = roots.filter(r => r.special);
            const drives = roots.filter(r => !r.special);

            sortAndRender();

            if (sidebarQuickAccess) {
                const icons = { 'Bureau': 'directory_closed-4.png', 'Documents': 'directory_closed-4.png', 'Téléchargements': 'directory_closed-4.png' };
                sidebarQuickAccess.innerHTML = specialDirs.map(r => `
                    <div class="sidebar-item flex items-center gap-2 px-2 py-0.5 hover:bg-[#e5f1fb] dark:hover:bg-[#333] text-[12px] text-[#333] dark:text-[#ccc] cursor-pointer" onclick="window.loadPath('${r.path.replace(/\\/g, '\\\\')}')">
                        <img src="https://win98icons.alexmeub.com/icons/png/${icons[r.name] || 'directory_closed-4.png'}" class="w-4 h-4" alt=""> 
                        <span class="truncate">${esc(r.name)}</span>
                    </div>
                `).join('');
            }
            if (sidebarDrives) {
                sidebarDrives.innerHTML = drives.map(r => `
                    <div class="sidebar-item flex items-center gap-2 px-2 py-0.5 hover:bg-[#e5f1fb] dark:hover:bg-[#333] text-[12px] text-[#333] dark:text-[#ccc] cursor-pointer" onclick="window.loadPath('${r.path.replace(/\\/g, '\\\\')}')">
                        <img src="https://win98icons.alexmeub.com/icons/png/hard_disk_drive-4.png" class="w-4 h-4" alt=""> 
                        <span class="truncate">${esc(r.name)}</span>
                    </div>
                `).join('');
            }
        } catch (e) { listEl.innerHTML = `<tr><td colspan="3" class="py-12 text-center text-red-500">${e.message}</td></tr>`; }
    };

    window.loadPath = async function(path) {
        if (!path) return window.loadRoots();
        setLoading();
        if (searchInput) searchInput.value = '';
        try {
            const data = await api(`browse_dirs.php?path=${encodeURIComponent(path)}`);
            addToHistory(path);
            currentPath = data.current;
            parentPath = data.parent;
            updateAddressBar(currentPath);
            updateNavButtons();
            btnUp.disabled = !parentPath;
            if (selectedInput) selectedInput.value = currentPath.split(/[\\\/]/).pop() || currentPath;
            currentDirs = data.dirs || [];
            sortAndRender();
        } catch (e) { listEl.innerHTML = `<tr><td colspan="3" class="py-12 text-center text-red-500">${e.message}</td></tr>`; }
    };

    btnRefresh?.addEventListener('click', () => {
        btnRefresh.classList.add('fa-spin');
        (currentPath === '' ? window.loadRoots() : window.loadPath(currentPath)).finally(() => btnRefresh.classList.remove('fa-spin'));
    });
    btnBack?.addEventListener('click', goBack);
    btnForward?.addEventListener('click', goForward);
    btnUp?.addEventListener('click', () => parentPath ? window.loadPath(parentPath) : window.loadRoots());
    btnSelect?.addEventListener('click', () => { if (currentPath) rootInput.value = currentPath; modal.close(); });
    searchInput?.addEventListener('input', () => {
        const q = searchInput.value.toLowerCase().trim();
        renderDirs(q === '' ? currentDirs : currentDirs.filter(d => d.name.toLowerCase().includes(q)));
    });

    btnBrowse?.addEventListener('click', async () => {
        const old = btnBrowse.innerHTML; btnBrowse.innerHTML = '<i class="fas fa-circle-notch animate-spin"></i>';
        const picked = await (async () => {
            try {
                const res = await fetch('native_picker.php');
                const data = await res.json();
                return data.ok ? data.path : null;
            } catch(e) { return null; }
        })();
        btnBrowse.innerHTML = old;
        if (picked) rootInput.value = picked; else { modal.showModal(); window.loadRoots(); }
    });
    btnClose?.addEventListener('click', () => modal.close());
})();
</script>

<?php require_once __DIR__ . '/footer.php'; ?>

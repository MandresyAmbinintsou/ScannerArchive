<?php
$pageTitle = "Répertoire - GED-MEF";
$currentPage = "index";
require_once 'app/header.php';
?>

<main id="mainContainer" class="mx-auto max-w-6xl px-4 py-12 transition-all duration-500 ease-in-out">
    
    <!-- Barre de Recherche Stylisée (Dark/Light) -->
    <section id="searchSection" class="mb-12">
        <div class="rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/5 p-3 shadow-xl transition-all duration-300">
            <div class="relative">
                <i class="fas fa-search absolute left-8 top-1/2 -translate-y-1/2 text-slate-400 dark:text-slate-500 text-xl"></i>
                <input id="searchInput" type="search" placeholder="Rechercher un dossier matricule..."
                       class="w-full rounded-2xl border-none bg-transparent py-5 pl-16 pr-8 text-lg font-black text-slate-900 dark:text-white outline-none placeholder:text-slate-400 dark:placeholder:text-slate-600 tracking-tight">
                <div id="totalBadge" class="absolute right-6 top-1/2 -translate-y-1/2 rounded-xl bg-slate-100 dark:bg-slate-900 px-4 py-2 text-[10px] font-black uppercase text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-400/20 shadow-inner">0 Items</div>
            </div>
        </div>
    </section>

    <!-- Layout Master-Detail -->
    <div id="layoutWrapper" class="flex flex-col gap-12 transition-none">
        
        <!-- Colonne GAUCHE : Répertoire -->
        <aside id="sidebarList" class="w-full max-w-3xl mx-auto transition-all duration-500 ease-in-out">
            <div class="flex items-center justify-between mb-8 px-6">
                <div>
                    <h2 class="text-[14px] font-black uppercase tracking-[0.5em] text-slate-900 dark:text-white">Répertoire</h2>
                    <p class="text-[9px] font-bold text-slate-400 dark:text-slate-500 mt-1 uppercase tracking-widest">Base de données archivée</p>
                </div>
                <button id="btnRefresh" class="h-12 w-12 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/5 text-slate-400 hover:text-indigo-600 transition shadow-lg">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
            
            <div id="matriculeList" class="flex flex-col gap-4 px-6">
                <!-- Rempli par JS -->
                <div class="py-20 text-center">
                    <i class="fas fa-circle-notch animate-spin text-indigo-600 text-3xl mb-4"></i>
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Chargement du répertoire...</p>
                </div>
            </div>

            <div id="pagination" class="mt-12 flex justify-center gap-2"></div>
        </aside>

        <!-- Colonne DROITE : Détails -->
        <section id="detailView" class="hidden flex-1 animate-in fade-in slide-in-from-right-12 duration-700">
            <div id="detailContent" class="md:sticky md:top-24 md:h-[calc(100vh-120px)] md:overflow-y-auto space-y-10 pr-4 scrollbar-thin">
                <!-- En-tête -->
                <div class="rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/5 p-10 shadow-2xl">
                    <div class="flex items-center justify-between mb-8">
                        <button id="btnBack" class="group flex items-center gap-3 rounded-xl bg-slate-900 px-6 py-3 text-[10px] font-black uppercase text-white hover:bg-indigo-600 transition shadow-2xl">
                            <i class="fas fa-arrow-left transition group-hover:-translate-x-1"></i> Retour Liste
                        </button>
                        <div class="flex gap-2">
                            <div class="h-1.5 w-1.5 rounded-full bg-slate-300 dark:bg-slate-600"></div>
                            <div class="h-1.5 w-1.5 rounded-full bg-slate-300 dark:bg-slate-600"></div>
                            <div class="h-1.5 w-1.5 rounded-full bg-indigo-500"></div>
                        </div>
                    </div>
                    <h2 id="detailTitle" class="text-5xl font-black text-slate-900 dark:text-white tracking-tighter uppercase leading-none italic"></h2>
                </div>

                <!-- Grille des Sous-dossiers -->
                <div id="folderBar" class="hidden items-center justify-between gap-4 rounded-2xl bg-white/70 dark:bg-slate-900/40 border border-slate-200 dark:border-white/5 px-5 py-4">
                    <button id="btnFolderBackTop" class="rounded-xl bg-slate-900 px-5 py-3 text-[9px] font-black uppercase tracking-widest text-white hover:bg-indigo-600 transition">
                        <i class="fas fa-arrow-left mr-2"></i> Retour
                    </button>
                    <div id="folderPath" class="flex-1 text-right text-[9px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500 truncate"></div>
                </div>
                <div id="sousdossierGrid" class="grid gap-3 grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6"></div>

                <!-- Galerie -->
                <div id="galerieSection" class="hidden rounded-3xl bg-slate-900 dark:bg-black border border-white/5 p-8 shadow-2xl">
                    <div class="mb-8 flex items-center justify-between border-b border-white/5 pb-6">
                        <div>
                            <h3 id="galerieTitle" class="text-xs font-black text-indigo-400 uppercase tracking-widest"></h3>
                            <p class="text-[8px] font-bold text-slate-500 mt-1 uppercase tracking-widest italic uppercase">Visualisation</p>
                        </div>
                        <button id="btnCloseGalerie" class="h-10 w-10 rounded-full bg-slate-800 text-white hover:bg-red-500 transition border border-white/5">
                            <i class="fas fa-times text-xs"></i>
                        </button>
                    </div>
                    <div class="mb-6 flex items-center justify-between">
                        <button id="btnFolderBack" class="hidden rounded-xl bg-slate-800 border border-white/10 px-5 py-3 text-[9px] font-black uppercase tracking-widest text-indigo-300 hover:bg-slate-700 transition">
                            <i class="fas fa-arrow-left mr-2"></i> Retour
                        </button>
                        <div></div>
                    </div>
                    <div id="galerieGrid" class="grid gap-2 grid-cols-4 md:grid-cols-6 lg:grid-cols-8"></div>
                </div>
            </div>
        </section>
    </div>

    <!-- Placeholder -->
    <div id="placeholder" class="hidden py-40 text-center">
        <div class="inline-flex h-24 w-24 items-center justify-center rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/5 text-slate-200 dark:text-slate-700 mb-8 shadow-2xl">
            <i class="fas fa-search text-4xl"></i>
        </div>
        <p class="text-xs font-black uppercase tracking-[0.4em] text-slate-400 dark:text-slate-500">Aucune archive correspondante</p>
    </div>

    <!-- Lightbox -->
    <div id="lightbox" class="fixed inset-0 z-[100] hidden items-center justify-center p-4 md:p-12 animate-in fade-in duration-300">
        <div id="lightboxOverlay" class="absolute inset-0 bg-slate-950/95 backdrop-blur-xl"></div>
        <div class="relative max-w-5xl w-full h-full flex flex-col items-center justify-center gap-8">
            <div class="flex gap-4">
                <a id="lightboxPrint" href="#" target="_blank" class="h-12 px-6 rounded-2xl bg-indigo-600 text-white hover:bg-indigo-700 transition flex items-center justify-center gap-3 text-[10px] font-black uppercase tracking-widest shadow-lg shadow-indigo-600/20">
                    <i class="fas fa-file-pdf text-lg"></i> Imprimer PDF
                </a>
                <button id="lightboxClose" class="h-12 w-12 rounded-2xl bg-white/10 text-white hover:bg-white/20 transition flex items-center justify-center group">
                    <i class="fas fa-times text-xl transition group-hover:rotate-90"></i>
                </button>
            </div>
            <img id="lightboxImg" class="max-h-[80vh] w-auto rounded-3xl shadow-2xl border border-white/10 object-contain" src="" alt="">
            <div id="lightboxCaption" class="text-center text-xs font-black uppercase tracking-[0.5em] text-indigo-400"></div>
        </div>
    </div>
</main>

<script src="public/js/app.js?v=<?php echo filemtime(__DIR__ . '/public/js/app.js'); ?>"></script>

<?php require_once 'app/footer.php'; ?>

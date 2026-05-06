<?php
$pageTitle = "Archive Viewer - GED-MEF";
$currentPage = "index";
require_once 'app/header.php';
?>

<div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
    <div class="absolute -top-24 left-1/2 h-[520px] w-[520px] -translate-x-1/2 rounded-full bg-brand/10 blur-3xl"></div>
    <div class="absolute -bottom-24 right-[-120px] h-[420px] w-[420px] rounded-full bg-indigo-500/10 blur-3xl"></div>
    <div class="absolute -bottom-36 left-[-120px] h-[360px] w-[360px] rounded-full bg-sky-500/10 blur-3xl"></div>
</div>

<main class="mx-auto grid max-w-7xl gap-6 px-4 py-6 sm:px-6 lg:grid-cols-[320px_minmax(0,1fr)]">
    <aside class="space-y-4">
        <div class="rounded-[32px] border border-slate-800 bg-slate-900/95 p-5 shadow-xl shadow-slate-950/40">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-xs uppercase tracking-[0.3em] text-slate-500">Matricules</p>
                    <h2 class="mt-2 text-2xl font-semibold text-white">Liste</h2>
                </div>
                <div id="totalBadge" class="rounded-full bg-brand px-3 py-1 text-sm font-semibold text-white">0</div>
            </div>

            <div class="mt-4 flex items-center gap-3">
                <label class="sr-only" for="searchInput">Rechercher</label>
                <input id="searchInput" type="search" placeholder="Rechercher un matricule…"
                       class="w-full rounded-3xl border border-slate-800 bg-slate-950/80 px-4 py-3 text-sm text-slate-200 placeholder:text-slate-500 outline-none transition focus:border-brand focus:ring-2 focus:ring-brand/30">
                <button id="btnRefresh" type="button" title="Rafraîchir"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-3xl border border-slate-800 bg-slate-950/80 text-slate-200 transition hover:bg-slate-800">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v6h6M20 20v-6h-6M5.64 18.36A9 9 0 1018.36 5.64"></path>
                    </svg>
                </button>
            </div>

            <div id="statsBar" class="mt-3 text-sm text-slate-400"></div>
        </div>

        <div id="matriculeList" class="space-y-3 overflow-y-auto rounded-[32px] border border-slate-800 bg-slate-900/95 p-3 shadow-xl shadow-slate-950/40" style="max-height:calc(100vh - 240px);">
            <div class="rounded-3xl border border-dashed border-slate-700 bg-slate-950/80 px-4 py-6 text-center text-sm text-slate-500">Chargement...</div>
        </div>
        <div id="pagination" class="flex flex-wrap items-center justify-center gap-2 rounded-[32px] border border-slate-800 bg-slate-900/95 p-4 text-sm text-slate-300"></div>
    </aside>

    <section class="space-y-6">
        <div class="rounded-[32px] border border-slate-800 bg-slate-900/95 p-6 shadow-xl shadow-slate-950/40">
            <div id="placeholder" class="flex min-h-[360px] flex-col items-center justify-center gap-4 rounded-[32px] border-2 border-dashed border-slate-800 bg-slate-950/60 p-10 text-center text-slate-500">
                <svg class="h-24 w-24 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                <p class="max-w-lg text-base leading-7">Sélectionnez un matricule pour afficher les sous-dossiers et explorer les images depuis votre archive.</p>
            </div>

            <div id="detailView" class="hidden space-y-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-[0.3em] text-slate-500">Détails du matricule</p>
                        <h2 id="detailTitle" class="mt-2 text-3xl font-semibold text-white"></h2>
                    </div>
                    <button id="btnBack" class="inline-flex items-center gap-2 rounded-3xl border border-slate-800 bg-slate-950/95 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">← Retour</button>
                </div>

                <div id="sousdossierGrid" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"></div>

                <div id="galerieSection" class="hidden rounded-[32px] border border-slate-800 bg-slate-950/95 p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-xs uppercase tracking-[0.3em] text-slate-500">Galerie</p>
                            <h3 id="galerieTitle" class="mt-2 text-xl font-semibold text-white"></h3>
                        </div>
                        <button id="btnCloseGalerie" class="rounded-3xl border border-slate-800 bg-slate-900/95 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">Fermer</button>
                    </div>
                    <div id="galerieGrid" class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3"></div>
                </div>
            </div>
        </div>
    </section>
</main>

<div id="lightbox" class="fixed inset-0 hidden items-center justify-center bg-slate-950/90 p-6">
    <div id="lightboxOverlay" class="absolute inset-0"></div>
    <div class="relative z-10 max-w-4xl overflow-hidden rounded-[32px] border border-slate-800 bg-slate-950 shadow-2xl">
        <button id="lightboxClose" class="absolute right-4 top-4 flex h-10 w-10 items-center justify-center rounded-full bg-slate-900/95 text-sm font-semibold text-white transition hover:bg-slate-800">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
        <img id="lightboxImg" class="w-full max-h-[80vh] object-contain" src="" alt="">
        <div id="lightboxCaption" class="border-t border-slate-800 bg-slate-900/95 px-6 py-4 text-sm text-slate-300"></div>
    </div>
</div>

<script src="public/js/app.js"></script>

<?php require_once 'app/footer.php'; ?>

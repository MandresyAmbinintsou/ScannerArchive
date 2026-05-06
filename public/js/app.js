// ============================================================
// app.js — Version Finale Robuste (Split-View + Thèmes)
// ============================================================

const BASE = window.__BASE_HREF__ || '';
const API = {
    matricules: `${BASE}app/matricules.php`,
    sousdossiers: `${BASE}app/sousdossiers.php`,
    images: `${BASE}app/images.php`,
    refresh: `${BASE}app/refresh.php`,
};

const state = {
    page: 1,
    limit: 50,
    search: '',
    searchTimeout: null,
    currentMatricule: null,
    isLoading: false,
    isSplitView: false,
};

const els = {
    mainContainer:  document.getElementById('mainContainer'),
    layoutWrapper:  document.getElementById('layoutWrapper'),
    sidebarList:    document.getElementById('sidebarList'),
    matriculeList:  document.getElementById('matriculeList'),
    pagination:     document.getElementById('pagination'),
    totalBadge:     document.getElementById('totalBadge'),
    searchInput:    document.getElementById('searchInput'),
    btnRefresh:     document.getElementById('btnRefresh'),
    placeholder:    document.getElementById('placeholder'),
    detailView:     document.getElementById('detailView'),
    detailTitle:    document.getElementById('detailTitle'),
    sousdossierGrid:document.getElementById('sousdossierGrid'),
    galerieSection: document.getElementById('galerieSection'),
    galerieTitle:   document.getElementById('galerieTitle'),
    galerieGrid:    document.getElementById('galerieGrid'),
    btnBack:        document.getElementById('btnBack'),
    btnCloseGalerie:document.getElementById('btnCloseGalerie'),
    lightbox:       document.getElementById('lightbox'),
    lightboxOverlay:document.getElementById('lightboxOverlay'),
    lightboxImg:    document.getElementById('lightboxImg'),
    lightboxCaption:document.getElementById('lightboxCaption'),
    lightboxClose:  document.getElementById('lightboxClose'),
};

/**
 * Helper Fetch
 */
async function apiFetch(url, params = {}) {
    const qs = new URLSearchParams(params).toString();
    const res = await fetch(`${url}?${qs}`);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
}

/**
 * Scan discret
 */
async function refreshArchive() {
    try {
        const res = await fetch(API.refresh, { cache: 'no-store' });
        return await res.json();
    } catch (err) { return null; }
}

/**
 * Chargement du répertoire
 */
async function loadMatricules() {
    if (state.isLoading) return;
    state.isLoading = true;

    try {
        const data = await apiFetch(API.matricules, {
            page:  state.page,
            limit: state.limit,
            q:     state.search,
        });

        const { data: rows, pagination } = data;
        
        if (els.totalBadge) els.totalBadge.textContent = `${pagination.total} Items`;

        if (rows.length === 0) {
            els.sidebarList.classList.add('hidden');
            els.placeholder.classList.remove('hidden');
            return;
        }

        els.sidebarList.classList.remove('hidden');
        els.placeholder.classList.add('hidden');

        // Rendu des Matricules (Agrandis & Stylisés)
        els.matriculeList.innerHTML = rows.map(m => `
            <div class="matricule-row group flex items-center justify-between rounded-[1.8rem] border-2 border-transparent bg-white dark:bg-slate-800 p-7 transition-all duration-300 hover:border-indigo-500 hover:shadow-2xl cursor-pointer ${state.currentMatricule?.id === m.id ? 'border-indigo-600 bg-indigo-50/50 dark:bg-slate-900 shadow-inner' : ''}"
                 data-id="${m.id}" data-nom="${escapeHtml(m.nom)}">
                <div class="flex items-center gap-6 overflow-hidden">
                    <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl ${state.currentMatricule?.id === m.id ? 'bg-indigo-600 text-white shadow-lg' : 'bg-slate-100 dark:bg-slate-900 text-slate-400 dark:text-slate-600 group-hover:bg-indigo-500 group-hover:text-white'} transition-all duration-300 font-black text-xl">
                        <i class="fas fa-folder-tree"></i>
                    </div>
                    <div class="truncate">
                        <div class="text-[20px] font-black text-slate-900 dark:text-white uppercase tracking-tight truncate">${escapeHtml(m.nom)}</div>
                        <div class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-[0.3em] mt-2 italic">${m.nb_sousdossiers} Dossiers Archivés</div>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-[11px] font-black text-indigo-600 dark:text-indigo-400 opacity-0 group-hover:opacity-100 transition duration-300 uppercase tracking-[0.2em] -translate-x-2 group-hover:translate-x-0">Consulter</span>
                    <i class="fas fa-chevron-right text-xs text-slate-300 dark:text-slate-600 group-hover:text-indigo-400 transition duration-300"></i>
                </div>
            </div>
        `).join('');

        renderPagination(pagination);
        attachMatriculeEvents();

    } catch (err) {
        console.error('Erreur API:', err);
        els.matriculeList.innerHTML = `<div class="py-20 text-center text-red-500 font-black uppercase text-[10px] tracking-widest italic">Erreur Système : Accès Base Refusé</div>`;
    } finally {
        state.isLoading = false;
    }
}

function attachMatriculeEvents() {
    els.matriculeList.querySelectorAll('.matricule-row').forEach(el => {
        el.addEventListener('click', () => {
            selectMatricule(parseInt(el.dataset.id), el.dataset.nom);
            els.matriculeList.querySelectorAll('.matricule-row').forEach(r => r.classList.remove('border-indigo-600', 'bg-indigo-50/50', 'dark:bg-slate-900', 'shadow-inner'));
            el.classList.add('border-indigo-600', 'bg-indigo-50/50', 'dark:bg-slate-900', 'shadow-inner');
        });
    });
}

function renderPagination({ page, totalPages }) {
    if (totalPages <= 1) { els.pagination.innerHTML = ''; return; }
    
    let html = '';
    const range = 1;
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= page - range && i <= page + range)) {
            html += `<button type="button" class="page-btn inline-flex h-11 w-11 items-center justify-center rounded-xl text-[11px] font-black transition-all duration-300 ${i === page ? 'bg-indigo-600 text-white shadow-lg' : 'bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/5 text-slate-500 hover:border-indigo-600 hover:text-indigo-600 dark:hover:text-white'}" data-page="${i}">${i}</button>`;
        } else if (i === page - range - 1 || i === page + range + 1) {
            html += `<span class="px-2 text-slate-400 text-xs">...</span>`;
        }
    }
    els.pagination.innerHTML = html;
    els.pagination.querySelectorAll('.page-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            state.page = parseInt(btn.dataset.page);
            loadMatricules();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });
}

/**
 * Sélection d'un matricule
 */
async function selectMatricule(id, nom) {
    if (state.currentMatricule?.id === id) return;
    state.currentMatricule = { id, nom };
    state.isSplitView = true;
    
    // Transition fluide du layout
    els.mainContainer.classList.replace('max-w-6xl', 'max-w-[1700px]');
    els.layoutWrapper.classList.replace('flex-col', 'md:flex-row');
    els.layoutWrapper.classList.add('items-start');
    
    els.sidebarList.classList.remove('w-full');
    els.sidebarList.classList.add('md:w-[450px]', 'shrink-0');
    
    // Affichage immédiat du loader dans la vue détail
    els.detailView.classList.remove('hidden');
    els.detailTitle.textContent = nom;
    els.sousdossierGrid.innerHTML = `
        <div class="col-span-full py-32 text-center">
            <div class="inline-flex h-16 w-16 items-center justify-center rounded-full border-4 border-indigo-600/20 border-t-indigo-600 animate-spin mb-6"></div>
            <p class="text-[10px] font-black uppercase tracking-[0.3em] text-slate-400 animate-pulse">Récupération des dossiers...</p>
        </div>
    `;
    els.galerieSection.classList.add('hidden');

    try {
        const data = await apiFetch(API.sousdossiers, { matricule_id: id });
        const rows = data.data;

        if (rows.length === 0) {
            els.sousdossierGrid.innerHTML = `
                <div class="col-span-full py-20 text-center rounded-3xl border-2 border-dashed border-slate-200 dark:border-white/5">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Aucun sous-dossier trouvé</p>
                </div>
            `;
            return;
        }

        // Sous-dossiers miniaturisés
        els.sousdossierGrid.innerHTML = rows.map(s => `
            <button type="button" class="sous-card group relative flex flex-col items-center justify-center rounded-2xl border border-slate-200 dark:border-white/5 bg-white dark:bg-slate-800 p-5 text-center transition-all duration-500 hover:border-indigo-600 hover:shadow-xl hover:-translate-y-1" data-id="${s.id}" data-nom="${escapeHtml(s.nom)}">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-slate-50 dark:bg-slate-900 text-indigo-500 transition-all duration-500 group-hover:bg-indigo-600 group-hover:text-white group-hover:rotate-6">
                    <i class="fas fa-folder-open text-lg"></i>
                </div>
                <div class="text-[11px] font-black text-slate-900 dark:text-white uppercase tracking-tight truncate w-full px-1">${escapeHtml(s.nom)}</div>
                <div class="mt-2 text-[8px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest italic">${s.nb_images} Images</div>
            </button>
        `).join('');

        els.sousdossierGrid.querySelectorAll('.sous-card').forEach(card => {
            card.addEventListener('click', () => loadGalerie(parseInt(card.dataset.id), card.dataset.nom));
        });

    } catch (err) {
        console.error('Erreur Sous-dossiers:', err);
        els.sousdossierGrid.innerHTML = `<div class="col-span-full py-10 text-center text-red-500 font-black uppercase text-[10px] border border-red-100 rounded-2xl bg-red-50/50">Erreur de chargement des données</div>`;
    }
}

/**
 * Galerie
 */
async function loadGalerie(sousDossierId, nom) {
    els.galerieSection.classList.remove('hidden');
    els.galerieTitle.textContent = nom;
    els.galerieGrid.innerHTML = `
        <div class="col-span-full py-10 text-center">
            <i class="fas fa-circle-notch animate-spin text-indigo-400 mb-2"></i>
            <p class="text-[9px] font-black text-indigo-400/60 uppercase tracking-widest italic">Chargement des visuels...</p>
        </div>
    `;

    els.galerieSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

    try {
        const data = await apiFetch(API.images, { sousdossier_id: sousDossierId });
        const rows = data.data;

        if (rows.length === 0) {
            els.galerieGrid.innerHTML = `<div class="col-span-full py-10 text-center text-slate-500 text-[10px] font-black uppercase">Aucune image disponible</div>`;
            return;
        }

        els.galerieGrid.innerHTML = rows.map(img => `
            <button type="button" class="img-card group relative aspect-square overflow-hidden rounded-xl bg-slate-100 dark:bg-slate-900 shadow-lg border border-white/5" data-url="${img.url}" data-nom="${escapeHtml(img.nom_fichier)}">
                <img class="h-full w-full object-cover transition duration-700 group-hover:scale-110 opacity-80 dark:opacity-50 group-hover:opacity-100" src="${img.url}" alt="${escapeHtml(img.nom_fichier)}" loading="lazy">
                <div class="absolute inset-0 bg-indigo-600/10 opacity-0 group-hover:opacity-100 transition duration-500"></div>
            </button>
        `).join('');

        els.galerieGrid.querySelectorAll('.img-card').forEach(card => {
            card.addEventListener('click', () => openLightbox(card.dataset.url, card.dataset.nom));
        });

    } catch (err) {
        els.galerieGrid.innerHTML = `<div class="col-span-full text-center text-red-500 font-black uppercase text-[10px]">Erreur Galerie</div>`;
    }
}

function openLightbox(url, nom) {
    els.lightboxImg.src = url;
    els.lightboxCaption.textContent = nom;
    els.lightbox.classList.remove('hidden');
    els.lightbox.classList.add('flex');
    document.body.style.overflow = 'hidden';
}
function closeLightbox() {
    els.lightbox.classList.add('hidden');
    els.lightbox.classList.remove('flex');
    els.lightboxImg.src = '';
    document.body.style.overflow = '';
}

// RETOUR : Reset layout
els.btnBack.addEventListener('click', () => {
    state.currentMatricule = null;
    state.isSplitView = false;
    
    els.detailView.classList.add('hidden');
    els.mainContainer.classList.replace('max-w-[1700px]', 'max-w-6xl');
    els.layoutWrapper.classList.replace('md:flex-row', 'flex-col');
    els.layoutWrapper.classList.remove('items-start');
    
    els.sidebarList.classList.remove('md:w-[450px]', 'shrink-0');
    els.sidebarList.classList.add('w-full');
    
    els.matriculeList.querySelectorAll('.matricule-row').forEach(r => r.classList.remove('border-indigo-600', 'bg-indigo-50/50', 'dark:bg-slate-900', 'shadow-inner'));
    window.scrollTo({ top: 0, behavior: 'smooth' });
});

// Supprimé : L'effet de survol qui masquait la vue détail car il nuisait à l'expérience "côte à côte"

els.btnCloseGalerie?.addEventListener('click', () => {
    els.galerieSection.classList.add('hidden');
});

els.lightboxClose?.addEventListener('click', closeLightbox);
els.lightboxOverlay?.addEventListener('click', closeLightbox);
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });

els.searchInput?.addEventListener('input', () => {
    clearTimeout(state.searchTimeout);
    state.searchTimeout = setTimeout(() => {
        state.search = els.searchInput.value.trim();
        state.page = 1;
        loadMatricules();
    }, 300);
});

els.btnRefresh?.addEventListener('click', async () => {
    els.btnRefresh.classList.add('animate-spin');
    await refreshArchive();
    loadMatricules();
    setTimeout(() => els.btnRefresh.classList.remove('animate-spin'), 1000);
});

/**
 * Initialisation
 */
window.addEventListener('DOMContentLoaded', () => {
    loadMatricules();
    // Le refresh automatique au chargement est désactivé car il vide la DB à chaque F5
    // refreshArchive().then(res => {
    //     if (res && res.ok) loadMatricules();
    // });
});

function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
}

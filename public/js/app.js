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
    hasMore: true,
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
    detailContent:  document.getElementById('detailContent'),
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
    lightboxPrint:  document.getElementById('lightboxPrint'),
    notificationToast: null,
};

/**
 * Système de Notification (Toast)
 */
function showToast(message, type = 'info') {
    if (!els.notificationToast) {
        const container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'fixed top-8 left-1/2 z-[200] flex flex-col gap-3 -translate-x-1/2 items-center';
        document.body.appendChild(container);
        els.notificationToast = container;
    }

    const toast = document.createElement('div');
    const colors = {
        success: 'bg-emerald-500',
        error: 'bg-red-500',
        info: 'bg-indigo-600',
        progress: 'bg-slate-800'
    };

    toast.className = `${colors[type]} text-white px-6 py-4 rounded-2xl shadow-2xl flex items-center gap-4 animate-in slide-in-from-right-full duration-500 border border-white/10`;
    toast.innerHTML = `
        <div class="flex-1">
            <p class="text-[10px] font-black uppercase tracking-widest">${message}</p>
        </div>
        <button class="text-white/50 hover:text-white transition"><i class="fas fa-times"></i></button>
    `;

    els.notificationToast.appendChild(toast);
    
    const close = () => {
        toast.classList.replace('animate-in', 'animate-out');
        toast.classList.add('fade-out', 'slide-out-to-right-full');
        setTimeout(() => toast.remove(), 500);
    };

    toast.querySelector('button').onclick = close;
    if (type !== 'progress') setTimeout(close, 5000);
    return toast;
}

/**
 * WebSocket pour le temps réel
 */
let socket = null;
function initSocket() {
    const dot = document.getElementById('realtimeStatusDot');
    try {
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const port = 8001;
        const host = window.location.hostname;
        socket = new WebSocket(`${protocol}//${host}:${port}`);

        socket.onopen = () => {
            if (dot) {
                dot.classList.replace('bg-slate-300', 'bg-emerald-500');
                dot.classList.remove('animate-pulse');
                dot.title = "Moteur temps réel connecté";
            }
        };

        socket.onmessage = (event) => {
            const data = json_decode_safe(event.data);
            if (!data) return;

            switch (data.type) {
                case 'status':
                    showToast(data.message, 'info');
                    break;
                case 'progress':
                    showToast(`Scan : ${data.message}`, 'progress');
                    break;
                case 'finish':
                    showToast("Répertoire mis à jour avec succès !", 'success');
                    if (!state.isSplitView) {
                        loadMatricules();
                    } else {
                        showToast("De nouveaux dossiers sont peut-être disponibles.", 'info');
                    }
                    break;
                case 'error':
                    showToast(data.message, 'error');
                    break;
            }
        };

        socket.onclose = () => {
            if (dot) {
                dot.classList.add('bg-slate-300', 'animate-pulse');
                dot.classList.remove('bg-emerald-500');
                dot.title = "Moteur temps réel déconnecté (reconnexion...)";
            }
            setTimeout(initSocket, 5000); 
        };

        socket.onerror = () => {
            if (dot) {
                dot.classList.add('bg-red-500');
                dot.classList.remove('bg-emerald-500', 'bg-slate-300', 'animate-pulse');
                dot.title = "Erreur de connexion au moteur temps réel";
            }
        };
    } catch (e) {
        console.warn("WebSocket non disponible");
    }
}

function json_decode_safe(str) {
    try { return JSON.parse(str); } catch(e) { return null; }
}

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
async function loadMatricules(append = false) {
    if (state.isLoading) return;
    state.isLoading = true;

    if (!append) {
        state.page = 1;
        els.matriculeList.innerHTML = `
            <div class="py-20 text-center">
                <i class="fas fa-circle-notch animate-spin text-indigo-600 text-3xl mb-4"></i>
                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Chargement...</p>
            </div>
        `;
    }

    try {
        const data = await apiFetch(API.matricules, {
            page:  state.page,
            limit: state.limit,
            q:     state.search,
        });

        const { data: rows, pagination } = data;
        state.hasMore = state.page < pagination.totalPages;
        
        if (els.totalBadge) els.totalBadge.textContent = `${pagination.total} Items`;

        if (rows.length === 0 && !append) {
            els.sidebarList.classList.add('hidden');
            els.placeholder.classList.remove('hidden');
            return;
        }

        els.sidebarList.classList.remove('hidden');
        els.placeholder.classList.add('hidden');

        const html = rows.map(m => `
            <div class="matricule-row group flex items-center justify-between rounded-[1.8rem] border-2 border-transparent bg-white dark:bg-slate-800 p-7 transition-all duration-300 hover:border-indigo-500 hover:shadow-2xl cursor-pointer ${state.currentMatricule?.id === m.id ? 'border-indigo-600 bg-indigo-50/50 dark:bg-slate-900 shadow-inner' : ''}"
                 data-id="${m.id}" data-nom="${escapeHtml(m.nom)}">
                <div class="flex items-center gap-6 overflow-hidden">
                    <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl ${state.currentMatricule?.id === m.id ? 'bg-indigo-600 text-white shadow-lg' : 'bg-slate-100 dark:bg-slate-900 text-slate-400 dark:text-slate-600 group-hover:bg-indigo-500 group-hover:text-white'} transition-all duration-300 font-black text-xl">
                        <i class="fas fa-user-circle"></i>
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

        if (append) {
            els.matriculeList.insertAdjacentHTML('beforeend', html);
        } else {
            els.matriculeList.innerHTML = html;
        }

        renderLoadMore();
        attachMatriculeEvents();

    } catch (err) {
        console.error('Erreur API:', err);
        if (!append) els.matriculeList.innerHTML = `<div class="py-20 text-center text-red-500 font-black uppercase text-[10px] tracking-widest italic">Erreur Système</div>`;
    } finally {
        state.isLoading = false;
    }
}

function renderLoadMore() {
    if (state.hasMore) {
        els.pagination.innerHTML = `
            <button id="btnLoadMore" class="group flex items-center gap-4 rounded-2xl bg-white dark:bg-slate-800 border-2 border-slate-200 dark:border-white/5 px-10 py-5 text-[11px] font-black uppercase tracking-widest text-slate-600 dark:text-slate-400 hover:border-indigo-600 hover:text-indigo-600 transition shadow-lg mx-auto block mt-8">
                <span>Voir plus de matricules</span>
                <i class="fas fa-plus transition group-hover:rotate-90"></i>
            </button>
        `;
        document.getElementById('btnLoadMore').addEventListener('click', () => {
            state.page++;
            loadMatricules(true);
        });
    } else {
        els.pagination.innerHTML = state.page > 1 ? `
            <div class="text-center mt-8">
                <p class="text-[9px] font-black uppercase tracking-[0.4em] text-slate-400 dark:text-slate-500 italic">Fin du répertoire</p>
            </div>
        ` : '';
    }
}

function attachMatriculeEvents() {
    els.matriculeList.querySelectorAll('.matricule-row').forEach(el => {
        el.addEventListener('click', () => {
            selectMatricule(parseInt(el.dataset.id), el.dataset.nom, el);
            els.matriculeList.querySelectorAll('.matricule-row').forEach(r => r.classList.remove('border-indigo-600', 'bg-indigo-50/50', 'dark:bg-slate-900', 'shadow-inner'));
            el.classList.add('border-indigo-600', 'bg-indigo-50/50', 'dark:bg-slate-900', 'shadow-inner');
        });
    });
}

/**
 * Sélection d'un matricule
 */
async function selectMatricule(id, nom, clickedEl = null) {
    if (state.currentMatricule?.id === id) return;
    
    // 1. Mémoriser la position et figer la hauteur pour éviter le "shrink"
    const scrollY = window.scrollY;
    document.body.style.minHeight = document.documentElement.scrollHeight + 'px';
    
    state.currentMatricule = { id, nom };
    state.isSplitView = true;
    
    // 2. Appliquer les changements de layout
    els.mainContainer.classList.replace('max-w-6xl', 'max-w-[1700px]');
    els.layoutWrapper.classList.replace('flex-col', 'md:flex-row');
    // Suppression de items-start pour que la colonne de droite s'étire sur toute la hauteur
    els.layoutWrapper.classList.remove('items-start'); 
    
    els.sidebarList.classList.remove('w-full', 'max-w-3xl', 'mx-auto');
    els.sidebarList.classList.add('md:w-[450px]', 'shrink-0');
    
    // 3. Affichage immédiat du bloc détail
    els.detailView.classList.remove('hidden');
    els.detailTitle.textContent = nom;
    els.detailContent.scrollTop = 0; // On remet le scroll interne du détail à zéro

    // 4. RESTAURER la position pour éviter le saut (important)
    window.scrollTo(0, scrollY);
    setTimeout(() => { document.body.style.minHeight = ''; }, 100);
    
    // 5. Charger les données
    els.galerieSection.classList.add('hidden');
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
    if (els.lightboxPrint) {
        els.lightboxPrint.href = `${BASE}app/print_pdf.php?url=${encodeURIComponent(url)}`;
    }
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
els.btnBack?.addEventListener('click', () => {
    const scrollY = window.scrollY;
    document.body.style.minHeight = document.documentElement.scrollHeight + 'px';

    state.currentMatricule = null;
    state.isSplitView = false;

    els.detailView.classList.add('hidden');
    els.mainContainer.classList.replace('max-w-[1700px]', 'max-w-6xl');
    els.layoutWrapper.classList.replace('md:flex-row', 'flex-col');

    els.sidebarList.classList.remove('md:w-[450px]', 'shrink-0');
    els.sidebarList.classList.add('w-full', 'max-w-3xl', 'mx-auto');

    els.matriculeList.querySelectorAll('.matricule-row').forEach(r => {
        r.classList.remove('border-indigo-600', 'bg-indigo-50/50', 'dark:bg-slate-900', 'shadow-inner');
    });

    window.scrollTo(0, scrollY);
    setTimeout(() => { document.body.style.minHeight = ''; }, 100);
});
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
    initSocket();
});

function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
}

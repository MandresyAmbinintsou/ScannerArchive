// ============================================================
// app.js — Navigation SPA sans rechargement de page
// ============================================================

const API = {
    matricules: 'app/matricules.php',
    sousdossiers: 'app/sousdossiers.php',
    images: 'app/images.php',
    refresh: 'app/refresh.php',
};

// ── État global ──────────────────────────────────────────────
const state = {
    page: 1,
    limit: 50,
    search: '',
    searchTimeout: null,
    currentMatricule: null,
    currentSousDossier: null,
    isLoading: false,
    hasMore: true,
};

// ── Éléments DOM ────────────────────────────────────────────
const els = {
    matriculeList:  document.getElementById('matriculeList'),
    pagination:     document.getElementById('pagination'),
    totalBadge:     document.getElementById('totalBadge'),
    statsBar:       document.getElementById('statsBar'),
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

// ── Fetch helper ────────────────────────────────────────────
async function apiFetch(url, params = {}) {
    const qs = new URLSearchParams(params).toString();
    const res = await fetch(`${url}?${qs}`);
    if (!res.ok) {
        let message = `HTTP ${res.status}`;
        try {
            const maybeJson = await res.json();
            message = maybeJson?.message || maybeJson?.error || message;
        } catch {
            // ignore
        }
        throw new Error(message);
    }
    return res.json();
}

async function refreshArchive() {
    try {
        const root = (arguments.length > 0 ? arguments[0] : '').trim?.() ?? '';
        const url = root ? `${API.refresh}?root=${encodeURIComponent(root)}` : API.refresh;
        const res = await fetch(url, { cache: 'no-store' });
        const json = await res.json().catch(() => null);
        if (!res.ok) throw new Error(json?.message || `HTTP ${res.status}`);
        if (!json?.ok) throw new Error(json?.message || 'Erreur de rafraîchissement');
        return json;
    } catch (err) {
        console.warn('Actualisation échouée :', err?.message || err);
        return null;
    }
}

// ── Charger la liste des matricules ─────────────────────────
async function loadMatricules(append = false) {
    if (state.isLoading || (!state.hasMore && append)) return;
    
    state.isLoading = true;
    if (!append) {
        state.page = 1;
        state.hasMore = true;
        els.matriculeList.innerHTML = '<div class="loading px-4 py-6 text-center text-slate-500">Chargement...</div>';
    } else {
        const loader = document.createElement('div');
        loader.id = 'scroll-loader';
        loader.className = 'px-4 py-4 text-center text-xs text-slate-500 animate-pulse';
        loader.textContent = 'Chargement de la suite...';
        els.matriculeList.appendChild(loader);
    }

    try {
        const data = await apiFetch(API.matricules, {
            page:  state.page,
            limit: state.limit,
            q:     state.search,
        });

        const { data: rows, pagination } = data;
        
        // Retirer le loader
        const oldLoader = document.getElementById('scroll-loader');
        if (oldLoader) oldLoader.remove();

        // Stats
        if (els.totalBadge) els.totalBadge.textContent = pagination.total.toLocaleString();
        if (els.statsBar) els.statsBar.textContent = `${pagination.total.toLocaleString()} matricules`;

        if (!append && rows.length === 0) {
            els.matriculeList.innerHTML = '<div class="empty px-4 py-10 text-center text-slate-500 italic">Aucun résultat</div>';
            return;
        }

        const html = rows.map(m => `
            <button type="button" class="matricule-item w-full rounded-3xl border border-slate-800 bg-slate-950/95 p-4 text-left transition hover:border-brand hover:bg-slate-900 ${state.currentMatricule?.id === m.id ? 'ring-2 ring-brand/50' : ''}"
                    data-id="${m.id}" data-nom="${escapeHtml(m.nom)}">
                <div class="flex items-center justify-between gap-3">
                    <span class="block max-w-[70%] truncate text-sm font-semibold text-white">${escapeHtml(m.nom)}</span>
                    <span class="rounded-full bg-slate-800 px-3 py-1 text-xs font-medium text-slate-300">${m.nb_sousdossiers}</span>
                </div>
            </button>
        `).join('');

        if (append) {
            els.matriculeList.insertAdjacentHTML('beforeend', html);
        } else {
            els.matriculeList.innerHTML = html;
        }

        // Événements click
        attachMatriculeEvents();

        state.hasMore = state.page < pagination.totalPages;
        state.page++;

    } catch (err) {
        els.matriculeList.innerHTML = `<div class="empty">Erreur : ${err.message}</div>`;
    } finally {
        state.isLoading = false;
    }
}

function attachMatriculeEvents() {
    els.matriculeList.querySelectorAll('.matricule-item:not([data-bound])').forEach(el => {
        el.setAttribute('data-bound', 'true');
        el.addEventListener('click', () => {
            const id  = parseInt(el.dataset.id);
            const nom = el.dataset.nom;
            selectMatricule(id, nom);
            els.matriculeList.querySelectorAll('.matricule-item').forEach(e => e.classList.remove('ring-2', 'ring-brand/50'));
            el.classList.add('ring-2', 'ring-brand/50');
        });
    });
}

// ── Détection du Scroll ──────────────────────────────────────
els.matriculeList.addEventListener('scroll', () => {
    const { scrollTop, scrollHeight, clientHeight } = els.matriculeList;
    if (scrollTop + clientHeight >= scrollHeight - 50) {
        loadMatricules(true);
    }
});

// ── Pagination ───────────────────────────────────────────────
function renderPagination({ page, totalPages }) {
    if (totalPages <= 1) { els.pagination.innerHTML = ''; return; }

    const pages = getPaginationRange(page, totalPages);
    els.pagination.innerHTML = pages.map(p => {
        if (p === '...') return `<span class="inline-flex items-center rounded-full bg-slate-800 px-3 py-1 text-xs text-slate-500">…</span>`;
        return `<button type="button" class="page-btn inline-flex items-center justify-center rounded-full px-3 py-2 text-sm transition ${p === page ? 'bg-brand text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700'}" data-page="${p}">${p}</button>`;
    }).join('');

    els.pagination.querySelectorAll('.page-btn[data-page]').forEach(btn => {
        btn.addEventListener('click', () => {
            state.page = parseInt(btn.dataset.page);
            loadMatricules();
        });
    });
}

function getPaginationRange(current, total) {
    const delta = 2;
    const range = [];
    const rangeWithDots = [];
    let l;
    for (let i = 1; i <= total; i++) {
        if (i === 1 || i === total || (i >= current - delta && i <= current + delta)) {
            range.push(i);
        }
    }
    for (let i of range) {
        if (l) {
            if (i - l === 2) rangeWithDots.push(l + 1);
            else if (i - l !== 1) rangeWithDots.push('...');
        }
        rangeWithDots.push(i);
        l = i;
    }
    return rangeWithDots;
}

// ── Sélectionner un matricule ────────────────────────────────
async function selectMatricule(id, nom) {
    state.currentMatricule = { id, nom };
    state.currentSousDossier = null;

    els.placeholder.style.display    = 'none';
    els.detailView.style.display     = 'block';
    els.galerieSection.style.display = 'none';
    els.detailTitle.textContent      = nom;
    els.sousdossierGrid.innerHTML    = '<div class="loading">Chargement des dossiers...</div>';

    try {
        const data = await apiFetch(API.sousdossiers, { matricule_id: id });
        const rows = data.data;

        if (rows.length === 0) {
            els.sousdossierGrid.innerHTML = '<div class="empty">Aucun sous-dossier</div>';
            return;
        }

        const icons = {
            affectation: 'clipboard-document',
            avance: 'banknotes',
            promotion: 'arrow-up',
            vacances: 'sun',
            supplement: 'plus',
            risque: 'exclamation-triangle',
            arretees: 'ban',
            decisionnaire: 'user-group',
            note: 'document-text',
            default: 'folder'
        };

        const iconPaths = {
            'clipboard-document': 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
            'banknotes': 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2-2H3a2 2 0 00-2 2v6a2 2 0 002 2h2.5a1 1 0 00.83-.445l1.415-1.415a1 1 0 011.41 0l1.415 1.415a1 1 0 00.83.445H17a2 2 0 002-2v-2a2 2 0 00-2-2zM9 11a1 1 0 11-2 0 1 1 0 012 0zM9 15a1 1 0 11-2 0 1 1 0 012 0zM13 11a1 1 0 11-2 0 1 1 0 012 0zM13 15a1 1 0 11-2 0 1 1 0 012 0z',
            'arrow-up': 'M5 10l7-7m0 0l7 7m-7-7v18',
            'sun': 'M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z',
            'plus': 'M12 6v6m0 0v6m0-6h6m-6 0H6',
            'exclamation-triangle': 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z',
            'ban': 'M18.364 18.364A9 9 0 005.636 5.636m0 0A9.009 9.009 0 0012 15a9.009 9.009 0 006.364-2.636zM9.152 9.152a4 4 0 005.696 0M9.152 9.152a4 4 0 015.696 5.696',
            'user-group': 'M17 20h5a2 2 0 002 2v-4.372a6.973 6.983 0 00-2.02-4.943l-.004-.003a6.959 6.959 0 00-11.968 0l-.004.003A6.973 6.983 0 003 16.628V22a2 2 0 002 2h5M12 13a4 4 0 100-8 4 0 000 8z',
            'document-text': 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
            'folder': 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H9a1 1 0 01-.9-.55L6.9 4.45A1 1 0 006 4H3a2 2 0 00-2 2z',
            'default': 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H9a1 1 0 01-.9-.55L6.9 4.45A1 1 0 006 4H3a2 2 0 00-2 2z'
        };

        els.sousdossierGrid.innerHTML = rows.map(s => {
            const key  = s.nom.toLowerCase().split(' ')[0];
            const iconKey = icons[key] || 'default';
            const iconPath = iconPaths[iconKey];
            return `
                <button type="button" class="sous-card group w-full rounded-3xl border border-slate-800 bg-slate-950/95 p-5 text-left transition hover:border-brand hover:bg-slate-900" data-id="${s.id}" data-nom="${escapeHtml(s.nom)}">
                    <div class="flex items-start gap-4">
                        <svg class="flex h-12 w-12 items-center justify-center rounded-3xl bg-slate-900 text-brand shadow-lg shadow-brand/10" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="${iconPath}"/>
                        </svg>
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-semibold text-white">${escapeHtml(s.nom)}</div>
                            <div class="mt-2 text-xs text-slate-400">${s.nb_images} image${s.nb_images > 1 ? 's' : ''}</div>
                        </div>
                    </div>
                </button>
            `;
        }).join('');

        els.sousdossierGrid.querySelectorAll('.sous-card').forEach(card => {
            card.addEventListener('click', () => {
                const id  = parseInt(card.dataset.id);
                const nom = card.dataset.nom;
                els.sousdossierGrid.querySelectorAll('.sous-card').forEach(c => c.classList.remove('ring-2', 'ring-brand/50'));
                card.classList.add('ring-2', 'ring-brand/50');
                loadGalerie(id, nom);
            });
        });

    } catch (err) {
        els.sousdossierGrid.innerHTML = `<div class="empty">Erreur : ${err.message}</div>`;
    }
}

// ── Charger la galerie ───────────────────────────────────────
async function loadGalerie(sousDossierId, nom) {
    state.currentSousDossier = { id: sousDossierId, nom };
    els.galerieSection.style.display = 'block';
    els.galerieTitle.textContent     = nom;
    els.galerieGrid.innerHTML        = '<div class="loading">Chargement des images...</div>';

    // Scroll vers la galerie
    els.galerieSection.scrollIntoView({ behavior: 'smooth', block: 'start' });

    try {
        const data = await apiFetch(API.images, { sousdossier_id: sousDossierId });
        const rows = data.data;

        if (rows.length === 0) {
            els.galerieGrid.innerHTML = '<div class="empty">Aucune image</div>';
            return;
        }

        els.galerieGrid.innerHTML = rows.map(img => `
            <button type="button" class="img-card group overflow-hidden rounded-3xl border border-slate-800 bg-slate-900/95 text-left transition hover:border-brand" data-url="${img.url}" data-nom="${escapeHtml(img.nom_fichier)}">
                <img class="h-48 w-full object-cover transition duration-300 group-hover:scale-105" src="${img.url}" alt="${escapeHtml(img.nom_fichier)}" loading="lazy">
                <div class="border-t border-slate-800 px-4 py-3 text-sm text-slate-300">${escapeHtml(img.nom_fichier)}</div>
            </button>
        `).join('');

        els.galerieGrid.querySelectorAll('.img-card').forEach(card => {
            card.addEventListener('click', () => {
                openLightbox(card.dataset.url, card.dataset.nom);
            });
        });

    } catch (err) {
        els.galerieGrid.innerHTML = `<div class="empty">Erreur : ${err.message}</div>`;
    }
}

// ── Lightbox ─────────────────────────────────────────────────
function openLightbox(url, nom) {
    els.lightboxImg.src         = url;
    els.lightboxCaption.textContent = nom;
    els.lightbox.style.display  = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeLightbox() {
    els.lightbox.style.display  = 'none';
    els.lightboxImg.src         = '';
    document.body.style.overflow = '';
}

// ── Événements ───────────────────────────────────────────────
els.btnBack.addEventListener('click', () => {
    els.detailView.style.display  = 'none';
    els.placeholder.style.display = 'flex';
    state.currentMatricule = null;
    els.matriculeList.querySelectorAll('.matricule-item').forEach(e => e.classList.remove('active'));
});

els.btnCloseGalerie.addEventListener('click', () => {
    els.galerieSection.style.display = 'none';
    els.sousdossierGrid.querySelectorAll('.sous-card').forEach(c => c.classList.remove('active'));
});

els.lightboxClose.addEventListener('click', closeLightbox);
els.lightboxOverlay.addEventListener('click', closeLightbox);
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });

// Recherche avec debounce (300ms)
els.searchInput?.addEventListener('input', () => {
    clearTimeout(state.searchTimeout);
    state.searchTimeout = setTimeout(() => {
        state.search = els.searchInput?.value?.trim?.() ?? '';
        state.page   = 1;
        loadMatricules();
    }, 300);
});

// Recharger la liste quand la page redevient visible ou est réactualisée
window.addEventListener('pageshow', () => {
    loadMatricules();
});
window.addEventListener('focus', () => {
    loadMatricules();
});

// ── Utilitaires ──────────────────────────────────────────────
function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// ── Init ─────────────────────────────────────────────────────
window.addEventListener('load', () => {
    loadMatricules();
    initWebSocket();
    // Scan intelligent en arrière-plan
    refreshArchive().then(() => loadMatricules());
});

// ── WebSocket & Temps Réel ──────────────────────────────────
function initWebSocket() {
    const isSwoole = window.location.port === '8000'; // Par convention pour notre serveur Swoole
    if (!isSwoole) return;

    const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
    const socket = new WebSocket(`${protocol}//${window.location.host}`);

    socket.onopen = () => console.log('Connecté au serveur de temps réel (Swoole)');
    
    socket.onmessage = (event) => {
        const msg = JSON.parse(event.data);
        if (msg.type === 'progress') {
            if (els.statsBar) {
                els.statsBar.innerHTML = `<svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>${msg.message} (${msg.percent}%)`;
                els.statsBar.classList.add('animate-pulse', 'text-brand');
            }
        }
        if (msg.type === 'finish') {
            if (els.statsBar) {
                els.statsBar.textContent = msg.message;
                els.statsBar.classList.remove('animate-pulse', 'text-brand');
            }
            loadMatricules();
        }
    };

    // Exposer une fonction globale pour déclencher le scan via WS
    window.triggerScan = () => {
        socket.send(JSON.stringify({ action: 'start_scan' }));
    };
}

// ── Événements additionnels ──────────────────────────────────
els.btnRefresh?.addEventListener('click', async () => {
    els.btnRefresh.classList.add('animate-spin');
    if (window.triggerScan && window.location.port === '8000') {
        window.triggerScan();
    } else {
        await refreshArchive();
        loadMatricules();
    }
    setTimeout(() => els.btnRefresh.classList.remove('animate-spin'), 1000);
});

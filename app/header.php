<?php
// app/header.php – En-tête unifié pour Archive Viewer / GED-MEF
session_start();
$baseHref = $baseHref ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? "GED-MEF Archive Viewer"; ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#eff6ff',
                            500: '#4f8ef7',
                            600: '#1e40af',
                            700: '#1e3a8a',
                            900: '#1e1b4b'
                        },
                        surface: '#071018',
                        panel: '#0f172a',
                        slate: { 950: '#020617' }
                    },
                    fontFamily: {
                        'mono': ['JetBrains Mono', 'ui-monospace', 'monospace']
                    },
                    borderRadius: { '3xl': '32px' }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/heroicons@2.0.18/outline.css">
</head>
<body class="min-h-screen bg-gradient-to-b from-slate-950 via-slate-950 to-slate-900 text-slate-100 antialiased selection:bg-brand/30">

    <header class="sticky top-0 z-50 border-b border-slate-800/80 bg-slate-950/70 backdrop-blur-md">
        <div class="mx-auto flex max-w-7xl items-center gap-4 px-4 py-4 sm:px-6">
            <!-- Logo / Titre -->
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-3xl bg-brand text-slate-900 shadow-lg shadow-brand/20">
                    <svg class="w-8 h-8 text-brand-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-[0.3em] text-slate-500">GED-MEF</p>
                    <h1 class="text-xl font-semibold text-white">Exploration d’archives</h1>
                </div>
            </div>

            <!-- Navigation (liens dynamiques) -->
            <div class="flex flex-1 items-center justify-end gap-4">
                <nav class="flex items-center gap-5 text-sm font-medium">
                    <a href="<?= $baseHref ?>index.php" class="transition <?= ($currentPage ?? '') === 'index' ? 'text-white border-b-2 border-brand' : 'text-slate-400 hover:text-white' ?>">Accueil</a>
                    
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <a href="<?= $baseHref ?>admin.php" class="transition <?= ($currentPage ?? '') === 'admin' ? 'text-white border-b-2 border-brand' : 'text-slate-400 hover:text-white' ?>">Administration</a>
                        <a href="<?= $baseHref ?>gestion_compte.php" class="transition <?= ($currentPage ?? '') === 'gestion_compte' ? 'text-white border-b-2 border-brand' : 'text-slate-400 hover:text-white' ?>">Comptes</a>
                    <?php endif; ?>
                </nav>

                <!-- Boutons utilitaires -->
                <div class="flex items-center gap-3">
                    <a href="<?= $baseHref ?>app/indexer.php" title="Indexer / réindexer l'archive"
                       class="inline-flex items-center gap-2 rounded-3xl border border-slate-800 bg-slate-950/60 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-slate-950/30 transition hover:bg-slate-900 <?= ($currentPage ?? '') === 'indexer' ? 'ring-2 ring-brand/40' : '' ?>">
                        <svg class="h-4 w-4 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v6h6M20 20v-6h-6M5.64 18.36A9 9 0 1018.36 5.64"></path>
                        </svg>
                        Indexer
                    </a>

                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <button onclick="manualBackup()" title="Sauvegarder la base de données" 
                                class="rounded-3xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-emerald-600/20 transition hover:bg-emerald-500">
                            💾 Sauvegarde
                        </button>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['username'])): ?>
                        <span class="text-sm text-slate-400"><?= htmlspecialchars($_SESSION['username']) ?></span>
                        <button onclick="confirmLogout()" 
                                class="rounded-3xl bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-red-600/20 transition hover:bg-red-500">
                            Déconnexion
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <script>
        window.__BASE_HREF__ = <?= json_encode($baseHref, JSON_UNESCAPED_SLASHES) ?>;

        function confirmLogout() {
            if (confirm("Êtes-vous sûr de vouloir vous déconnecter ?")) {
                window.location.href = `${window.__BASE_HREF__}logout.php`;
            }
        }

        async function manualBackup() {
            try {
                const response = await fetch(`${window.__BASE_HREF__}api/backup-db.php`);
                const data = await response.json();
                if (data.success) {
                    alert("Sauvegarde réussie : " + data.file);
                } else {
                    alert("Erreur de sauvegarde : " + data.error);
                }
            } catch (error) {
                alert("Erreur réseau lors de la sauvegarde.");
            }
        }

        setInterval(() => {
            const now = new Date();
            if (now.getHours() === 15 && now.getMinutes() === 0) {
                fetch(`${window.__BASE_HREF__}api/backup-db.php`);
            }
        }, 60000);

        window.addEventListener('beforeunload', () => {
            navigator.sendBeacon(`${window.__BASE_HREF__}api/backup-db.php`);
        });
    </script>

<?php
// app/header.php – Version Dark/Light avec Toggle
require_once __DIR__ . '/auth.php';
require_login();
$baseHref = $baseHref ?? '';
?>
<!DOCTYPE html>
<html lang="fr" class="dark">
<head>
    <meta charset="UTF-8">
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
                            DEFAULT: '#6366f1',
                            dark: '#4f46e5'
                        },
                        stylized: {
                            light: '#1e293b', // slate-800
                            base: '#0f172a',  // slate-900
                            dark: '#334155'   // slate-700
                        }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="min-h-screen bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200 antialiased font-sans transition-colors duration-300">

    <!-- Header Dynamique (Thème-aware) -->
    <header class="sticky top-0 z-50 border-b border-slate-200 dark:border-white/5 bg-white dark:bg-slate-950 text-slate-800 dark:text-white shadow-sm dark:shadow-2xl transition-colors duration-300">
        <div class="mx-auto flex max-w-[1700px] items-center justify-between gap-4 px-8 py-4">
            <!-- Logo -->
            <a href="<?= $baseHref ?>index.php" class="flex items-center gap-4 group">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand text-white shadow-lg transition group-hover:rotate-6">
                    <i class="fas fa-database text-xl"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-lg font-black uppercase tracking-widest leading-none text-slate-900 dark:text-white">GED <span class="text-indigo-600 dark:text-indigo-400">-MEF</span></h1>
                        <div id="realtimeStatusDot" class="h-2 w-2 rounded-full bg-slate-300 animate-pulse" title="Vérification du moteur temps réel..."></div>
                    </div>
                    <p class="text-[9px] font-bold text-slate-400 dark:text-slate-500 mt-1 uppercase tracking-[0.3em]">Systeme nu</p>
                </div>
            </a>

            <!-- Navigation -->
            <nav class="hidden md:flex items-center gap-10 text-[11px] font-black uppercase tracking-[0.3em]">
                <a href="<?= $baseHref ?>index.php" class="transition <?= ($currentPage ?? '') === 'index' ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-500 hover:text-slate-900 dark:hover:text-white' ?>">Répertoire</a>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <a href="<?= $baseHref ?>app/indexer.php" class="transition <?= ($currentPage ?? '') === 'indexer' ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-500 hover:text-slate-900 dark:hover:text-white' ?>">Indexation</a>
                    <a href="<?= $baseHref ?>app/server_status.php" class="transition <?= ($currentPage ?? '') === 'status' ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-500 hover:text-slate-900 dark:hover:text-white' ?>">État Serveur</a>
                <?php endif; ?>
                <?php if (!isset($_SESSION['username'])): ?>
                    <a href="<?= $baseHref ?>app/login.php" class="transition text-slate-500 hover:text-slate-900 dark:hover:text-white">Connexion</a>
                    <a href="<?= $baseHref ?>app/formulaire.php" class="transition text-slate-500 hover:text-slate-900 dark:hover:text-white">Inscription</a>
                <?php elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <a href="<?= $baseHref ?>app/gestion_compte.php" class="transition text-slate-500 hover:text-slate-900 dark:hover:text-white">Gestion comptes</a>
                <?php endif; ?>
            </nav>

            <!-- Actions & Theme Toggle -->
            <div class="flex items-center gap-5">
                <button id="themeToggle" class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-white/5 text-slate-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-white transition">
                    <i class="fas fa-sun hidden dark:block"></i>
                    <i class="fas fa-moon block dark:hidden"></i>
                </button>

                <?php if (isset($_SESSION['username'])): ?>
                    <button onclick="confirmLogout()" class="rounded-xl bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-white/5 px-5 py-2.5 text-[10px] font-black uppercase text-red-500 dark:text-red-400 hover:bg-red-500 hover:text-white transition">
                        Quitter
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <script>
        window.__BASE_HREF__ = <?= json_encode($baseHref, JSON_UNESCAPED_SLASHES) ?>;
        
        // Theme Management
        const html = document.documentElement;
        const toggle = document.getElementById('themeToggle');
        
        if (localStorage.theme === 'light') {
            html.classList.remove('dark');
        }

        toggle.addEventListener('click', () => {
            if (html.classList.contains('dark')) {
                html.classList.remove('dark');
                localStorage.theme = 'light';
            } else {
                html.classList.add('dark');
                localStorage.theme = 'dark';
            }
        });

        function confirmLogout() {
            if (confirm("Voulez-vous quitter ?")) {
                window.location.href = `${window.__BASE_HREF__}logout.php`;
            }
        }
    </script>

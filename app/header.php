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
                <?php if (isset($_SESSION['username']) && sessionAccessLevel() <= ACCESS_LEVEL_ADMIN): ?>
                    <a href="<?= $baseHref ?>app/indexer.php" class="transition <?= ($currentPage ?? '') === 'indexer' ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-500 hover:text-slate-900 dark:hover:text-white' ?>">Indexation</a>
                    <a href="<?= $baseHref ?>app/server_status.php" class="transition <?= ($currentPage ?? '') === 'status' ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-500 hover:text-slate-900 dark:hover:text-white' ?>">État Serveur</a>
                <?php endif; ?>
                <?php if (!isset($_SESSION['username'])): ?>
                    <a href="<?= $baseHref ?>app/login.php" class="transition text-slate-500 hover:text-slate-900 dark:hover:text-white">Connexion</a>
                    <a href="<?= $baseHref ?>app/formulaire.php" class="transition text-slate-500 hover:text-slate-900 dark:hover:text-white">Inscription</a>
                <?php elseif (sessionAccessLevel() === ACCESS_LEVEL_SUPERADMIN): ?>
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
                    <div class="relative">
                        <button id="userMenuBtn" class="flex items-center gap-3 rounded-2xl bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-white/5 p-1.5 pr-4 transition hover:bg-slate-200 dark:hover:bg-slate-800">
                            <div class="h-8 w-8 rounded-full bg-indigo-600 flex items-center justify-center text-white text-[10px] font-black uppercase shadow-lg shadow-indigo-500/20">
                                <?= strtoupper(substr($_SESSION['username'], 0, 1)) ?>
                            </div>
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-700 dark:text-slate-300"><?= htmlspecialchars($_SESSION['username']) ?></span>
                            <i class="fas fa-chevron-down text-[8px] text-slate-400"></i>
                        </button>

                        <!-- User Dropdown Menu -->
                        <div id="userDropdown" class="absolute right-0 mt-3 w-64 origin-top-right rounded-[2rem] border border-slate-200 dark:border-white/5 bg-white dark:bg-slate-950 shadow-2xl transition-all duration-200 opacity-0 scale-95 pointer-events-none z-50 overflow-hidden">
                            <div class="p-6 border-b border-slate-100 dark:border-white/5">
                                <p class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 mb-1">Connecté en tant que</p>
                                <p class="text-sm font-black text-slate-900 dark:text-white truncate"><?= htmlspecialchars($_SESSION['username']) ?></p>
                            </div>
                            <div class="p-2">
                                <a href="<?= $baseHref ?>app/user_profil.php" class="flex items-center gap-4 px-4 py-3 rounded-2xl text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-white/5 hover:text-indigo-600 dark:hover:text-white transition">
                                    <div class="h-8 w-8 rounded-xl bg-slate-100 dark:bg-slate-900 flex items-center justify-center"><i class="fas fa-user-circle text-xs"></i></div>
                                    <div class="flex flex-col">
                                        <span class="text-[10px] font-black uppercase tracking-widest">À propos</span>
                                        <span class="text-[8px] font-bold opacity-50 uppercase">Voir mon profil</span>
                                    </div>
                                </a>
                                <a href="<?= $baseHref ?>app/user_profil.php" class="flex items-center gap-4 px-4 py-3 rounded-2xl text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-white/5 hover:text-indigo-600 dark:hover:text-white transition">
                                    <div class="h-8 w-8 rounded-xl bg-slate-100 dark:bg-slate-900 flex items-center justify-center"><i class="fas fa-lock text-xs"></i></div>
                                    <div class="flex flex-col">
                                        <span class="text-[10px] font-black uppercase tracking-widest">Mon mot de passe</span>
                                        <span class="text-[8px] font-bold opacity-50 uppercase">Sécurité du compte</span>
                                    </div>
                                </a>
                                <div class="my-2 border-t border-slate-100 dark:border-white/5 mx-4"></div>
                                <button onclick="confirmLogout()" class="w-full flex items-center gap-4 px-4 py-3 rounded-2xl text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 transition text-left">
                                    <div class="h-8 w-8 rounded-xl bg-red-50 dark:bg-red-900/20 flex items-center justify-center"><i class="fas fa-power-off text-xs"></i></div>
                                    <span class="text-[10px] font-black uppercase tracking-widest">Déconnexion</span>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Logout Confirmation Modal -->
    <div id="logoutModal" class="fixed inset-0 z-[200] invisible flex items-center justify-center p-4">
        <div id="logoutOverlay" class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm opacity-0 transition-opacity duration-300 ease-in-out"></div>
        <div id="logoutContent" class="relative w-full max-w-sm bg-white dark:bg-slate-900 shadow-2xl scale-95 opacity-0 transition-all duration-300 ease-out border border-slate-200 dark:border-white/5 rounded-[2rem] p-8 text-center">
            <div class="mx-auto h-16 w-16 rounded-2xl bg-red-50 dark:bg-red-900/20 text-red-500 flex items-center justify-center mb-6 shadow-inner">
                <i class="fas fa-power-off text-2xl"></i>
            </div>
            <h3 class="text-lg font-black text-slate-900 dark:text-white uppercase tracking-tighter mb-2">Déconnexion</h3>
            <p class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-8 leading-relaxed">Voulez-vous déconnecter la session ?</p>
            
            <div class="grid grid-cols-2 gap-4">
                <button onclick="closeLogoutModal()" class="rounded-xl bg-slate-100 dark:bg-slate-800 px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700 transition">
                    Annuler
                </button>
                <a href="<?= $baseHref ?>logout.php" class="rounded-xl bg-red-500 px-6 py-4 text-[10px] font-black uppercase tracking-widest text-white shadow-lg shadow-red-500/20 hover:bg-red-600 hover:scale-[1.02] transition flex items-center justify-center">
                    Quitter
                </a>
            </div>
        </div>
    </div>

    <script>
        window.__BASE_HREF__ = <?= json_encode($baseHref, JSON_UNESCAPED_SLASHES) ?>;
        
        // Theme Management
        const html = document.documentElement;
        const toggle = document.getElementById('themeToggle');
        
        if (localStorage.theme === 'light') {
            html.classList.remove('dark');
        }

        if (toggle) {
            toggle.addEventListener('click', () => {
                if (html.classList.contains('dark')) {
                    html.classList.remove('dark');
                    localStorage.theme = 'light';
                } else {
                    html.classList.add('dark');
                    localStorage.theme = 'dark';
                }
            });
        }

        // User Dropdown Management
        const userMenuBtn = document.getElementById('userMenuBtn');
        const userDropdown = document.getElementById('userDropdown');

        if (userMenuBtn && userDropdown) {
            userMenuBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                const isHidden = userDropdown.classList.contains('opacity-0');
                if (isHidden) {
                    userDropdown.classList.remove('opacity-0', 'scale-95', 'pointer-events-none', 'mt-3');
                    userDropdown.classList.add('opacity-100', 'scale-100', 'mt-2');
                } else {
                    userDropdown.classList.add('opacity-0', 'scale-95', 'pointer-events-none', 'mt-3');
                    userDropdown.classList.remove('opacity-100', 'scale-100', 'mt-2');
                }
            });

            document.addEventListener('click', (e) => {
                if (!userDropdown.contains(e.target) && !userMenuBtn.contains(e.target)) {
                    userDropdown.classList.add('opacity-0', 'scale-95', 'pointer-events-none', 'mt-3');
                    userDropdown.classList.remove('opacity-100', 'scale-100', 'mt-2');
                }
            });
        }

        function confirmLogout() {
            const modal = document.getElementById('logoutModal');
            const overlay = document.getElementById('logoutOverlay');
            const content = document.getElementById('logoutContent');

            modal.classList.remove('invisible');
            setTimeout(() => {
                overlay.classList.add('opacity-100');
                content.classList.remove('scale-95', 'opacity-0');
                content.classList.add('scale-100', 'opacity-100');
            }, 10);
            
            document.body.style.overflow = 'hidden';
        }

        function closeLogoutModal() {
            const modal = document.getElementById('logoutModal');
            const overlay = document.getElementById('logoutOverlay');
            const content = document.getElementById('logoutContent');

            overlay.classList.remove('opacity-100');
            content.classList.remove('scale-100', 'opacity-100');
            content.classList.add('scale-95', 'opacity-0');
            
            setTimeout(() => {
                modal.classList.add('invisible');
                document.body.style.overflow = '';
            }, 300);
        }

        // Close logout modal on overlay click
        const logoutOverlay = document.getElementById('logoutOverlay');
        if (logoutOverlay) {
            logoutOverlay.onclick = closeLogoutModal;
        }
    </script>

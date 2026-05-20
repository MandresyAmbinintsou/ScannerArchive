<?php
require_once 'auth.php';

if (isset($_SESSION['username'])) {
    header('Location: ../index.php');
    exit;
}

// Si aucun utilisateur n'existe, inviter à créer le premier compte.
if (!hasUsers()) {
    header('Location: formulaire.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (login($_POST['username'], $_POST['password'])) {
        header('Location: ../index.php');
        exit;
    } else {
        $error = $_SESSION['login_error'] ?? "Identifiants incorrects";
        unset($_SESSION['login_error']);
    }
}
?>
<!DOCTYPE html>
<html lang="fr" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - GED-MEF</title>
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
                        }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="min-h-screen bg-slate-50 dark:bg-slate-950 flex items-center justify-center p-4 antialiased font-sans transition-colors duration-300">

    <div class="w-full max-w-md animate-in fade-in zoom-in duration-500">
        <!-- Logo/Header -->
        <div class="text-center mb-10">
            <div class="inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-indigo-600 text-white shadow-xl mb-6">
                <i class="fas fa-database text-2xl"></i>
            </div>
            <h1 class="text-3xl font-black uppercase tracking-tighter text-slate-900 dark:text-white italic">
                GED <span class="text-indigo-600 dark:text-indigo-400">-MEF</span>
            </h1>
            <p class="text-[10px] font-black uppercase tracking-[0.4em] text-slate-400 mt-2">Accès Sécurisé</p>
        </div>

        <!-- Card -->
        <div class="rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/5 p-10 shadow-2xl">
            <?php if ($error): ?>
                <div class="mb-6 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-900/50 p-4 text-[10px] font-black uppercase tracking-widest text-red-600 dark:text-red-400 flex items-center gap-3">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div class="space-y-2">
                    <label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Utilisateur</label>
                    <div class="relative group">
                        <i class="fas fa-user absolute left-5 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-indigo-500 transition"></i>
                        <input type="text" name="username" required autofocus
                               class="w-full rounded-2xl border-none bg-slate-50 dark:bg-slate-950 py-4 pl-14 pr-6 text-sm font-bold text-slate-900 dark:text-white shadow-inner focus:ring-4 focus:ring-indigo-600/10 transition outline-none">
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Mot de passe</label>
                    <div class="relative group">
                        <i class="fas fa-lock absolute left-5 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-indigo-500 transition"></i>
                        <input type="password" name="password" id="password" required
                               class="w-full rounded-2xl border-none bg-slate-50 dark:bg-slate-950 py-4 pl-14 pr-12 text-sm font-bold text-slate-900 dark:text-white shadow-inner focus:ring-4 focus:ring-indigo-600/10 transition outline-none">
                        <button type="button" onclick="togglePassword('password', this)" class="absolute right-5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-indigo-500 transition">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" 
                        class="w-full rounded-2xl bg-indigo-600 py-5 text-[11px] font-black uppercase tracking-widest text-white shadow-lg shadow-indigo-600/20 hover:bg-indigo-700 hover:scale-[1.02] transition active:scale-95">
                    Se connecter
                </button>
            </form>

            <div class="mt-8 pt-8 border-t border-slate-100 dark:border-white/5 text-center">
                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">
                    Pas encore de compte ? 
                    <a href="formulaire.php" class="text-indigo-600 dark:text-indigo-400 hover:underline ml-1">Créer un compte</a>
                </p>
            </div>
        </div>

        <!-- Footer -->
        <p class="mt-10 text-center text-[9px] font-black uppercase tracking-[0.5em] text-slate-400 dark:text-slate-600">
            &copy; <?php echo date('Y'); ?> - GED - MEF
        </p>
    </div>

    <script>
        function togglePassword(id, btn) {
            const input = document.getElementById(id);
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        // Check system preference
        if (localStorage.theme === 'light' || (!('theme' in localStorage) && !window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.remove('dark')
        } else {
            document.documentElement.classList.add('dark')
        }
    </script>
</body>
</html>

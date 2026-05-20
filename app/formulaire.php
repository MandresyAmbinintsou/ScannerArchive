<?php
// formulaire.php - Formulaire de création de compte utilisateur
require_once 'auth.php';

$message = '';
$error = '';
$firstSetup = !hasUsers();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($username) || empty($password) || empty($confirm_password)) {
        $error = 'Tous les champs sont requis.';
    } elseif ($password !== $confirm_password) {
        $error = 'Les mots de passe ne correspondent pas.';
    } elseif (strlen($password) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères.';
    } else {
        $role = $firstSetup ? 'admin' : 'user';
        // Après le premier admin, un compte créé via ce formulaire doit être approuvé par l'admin.
        $approved = $firstSetup ? true : false;
        if (createUser($username, $password, $role, $approved)) {
            if ($approved) {
                $message = 'Compte admin créé avec succès ! <a href="login.php">Se connecter</a>';
            } else {
                $message = 'Compte créé. En attente de validation administrateur. <a href="login.php">Retour connexion</a>';
            }
        } else {
            $error = 'Erreur lors de la création du compte. Le nom d\'utilisateur existe peut-être déjà.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer un compte - GED-MEF</title>
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
        <!-- Header -->
        <div class="text-center mb-10">
            <div class="inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-indigo-600 text-white shadow-xl mb-6">
                <i class="fas fa-user-plus text-2xl"></i>
            </div>
            <h1 class="text-3xl font-black uppercase tracking-tighter text-slate-900 dark:text-white italic">Inscription</h1>
            <p class="text-[10px] font-black uppercase tracking-[0.4em] text-slate-400 mt-2">Nouveau Compte Système</p>
            <?php if ($firstSetup): ?>
                <div class="mt-4 inline-block px-4 py-1.5 rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 text-[9px] font-black uppercase tracking-widest border border-amber-200 dark:border-amber-900/50">
                    Configuration Initiale : Compte Admin
                </div>
            <?php endif; ?>
        </div>

        <!-- Card -->
        <div class="rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/5 p-10 shadow-2xl">
            <?php if ($error): ?>
                <div class="mb-6 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-900/50 p-4 text-[10px] font-black uppercase tracking-widest text-red-600 dark:text-red-400 flex items-center gap-3">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($message): ?>
                <div class="mb-6 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-900/50 p-4 text-[10px] font-black uppercase tracking-widest text-emerald-600 dark:text-emerald-400 flex items-center gap-3">
                    <i class="fas fa-check-circle"></i>
                    <div><?php echo $message; ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div class="space-y-2">
                    <label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Nom d'utilisateur</label>
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

                <div class="space-y-2">
                    <label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Confirmer</label>
                    <div class="relative group">
                        <i class="fas fa-shield-alt absolute left-5 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-indigo-500 transition"></i>
                        <input type="password" name="confirm_password" id="confirm_password" required
                               class="w-full rounded-2xl border-none bg-slate-50 dark:bg-slate-950 py-4 pl-14 pr-12 text-sm font-bold text-slate-900 dark:text-white shadow-inner focus:ring-4 focus:ring-indigo-600/10 transition outline-none">
                        <button type="button" onclick="togglePassword('confirm_password', this)" class="absolute right-5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-indigo-500 transition">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" 
                        class="w-full rounded-2xl bg-indigo-600 py-5 text-[11px] font-black uppercase tracking-widest text-white shadow-lg shadow-indigo-600/20 hover:bg-indigo-700 hover:scale-[1.02] transition active:scale-95">
                    Créer le compte
                </button>
            </form>

            <div class="mt-8 pt-8 border-t border-slate-100 dark:border-white/5 text-center">
                <a href="login.php" class="text-[10px] font-black uppercase tracking-widest text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                    <i class="fas fa-arrow-left mr-2"></i> Retour à la connexion
                </a>
            </div>
        </div>

        <p class="mt-10 text-center text-[9px] font-black uppercase tracking-[0.5em] text-slate-400 dark:text-slate-600">
            &copy; <?php echo date('Y'); ?> - GED DIRECTION NUMÉRIQUE
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

        if (localStorage.theme === 'light' || (!('theme' in localStorage) && !window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.remove('dark')
        } else {
            document.documentElement.classList.add('dark')
        }
    </script>
</body>
</html>

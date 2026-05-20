<?php
// app/user_profil.php — Gestion du profil par l'utilisateur lui-même
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

require_login();

$db = getDB();
$message = '';
$error = '';
$username = $_SESSION['username'];

// Charger les informations de l'utilisateur connecté
$stmt = $db->prepare('SELECT * FROM users WHERE username = :username');
$stmt->execute([':username' => $username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    // Session invalide ou utilisateur supprimé
    session_destroy();
    header('Location: login.php');
    exit;
}

// Traitement du changement de mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    try {
        $oldPwd = $_POST['old_password'];
        $newPwd = $_POST['new_password'];
        $confirmPwd = $_POST['confirm_password'];

        if (!password_verify($oldPwd, $user['password_hash'])) {
            throw new Exception("L'ancien mot de passe est incorrect.");
        }

        if (strlen($newPwd) < 4) {
            throw new Exception("Le nouveau mot de passe est trop court (min 4 caractères).");
        }

        if ($newPwd !== $confirmPwd) {
            throw new Exception("Les nouveaux mots de passe ne correspondent pas.");
        }

        $hash = password_hash($newPwd, PASSWORD_DEFAULT);
        $update = $db->prepare('UPDATE users SET password_hash = :hash, password_plain = :plain WHERE id = :id');
        $update->execute([
            ':hash' => $hash,
            ':plain' => $newPwd,
            ':id' => $user['id']
        ]);
        $message = "Mot de passe modifié avec succès.";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$pageTitle = "Mon Profil - " . htmlspecialchars($username);
$currentPage = "profil";
$baseHref = "../";

require_once __DIR__ . '/header.php';
?>

<main class="mx-auto max-w-4xl px-4 py-12">
    <div class="mb-12 flex items-center justify-between">
        <div>
            <h2 class="text-4xl font-black text-slate-900 dark:text-white tracking-tighter uppercase">Mon Profil</h2>
            <p class="mt-2 text-xs font-black uppercase tracking-widest text-indigo-600">Gérez vos informations personnelles</p>
        </div>
        <a href="../index.php" class="text-[10px] font-black uppercase tracking-widest text-slate-400 hover:text-slate-900 dark:hover:text-white transition flex items-center gap-2">
            <i class="fas fa-arrow-left"></i> Retour au répertoire
        </a>
    </div>

    <?php if ($message): ?>
        <div class="mb-8 rounded-2xl bg-emerald-50 border border-emerald-200 p-5 text-xs font-black uppercase tracking-widest text-emerald-600 flex items-center gap-3 animate-in fade-in slide-in-from-top-4 duration-300">
            <i class="fas fa-check-circle text-lg"></i> <?= $message ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="mb-8 rounded-2xl bg-red-50 border border-red-200 p-5 text-xs font-black uppercase tracking-widest text-red-600 flex items-center gap-3 animate-in fade-in slide-in-from-top-4 duration-300">
            <i class="fas fa-exclamation-circle text-lg"></i> <?= $error ?>
        </div>
    <?php endif; ?>

    <div class="grid gap-8 lg:grid-cols-3">
        <!-- Carte de Profil -->
        <section class="lg:col-span-1">
            <div class="rounded-3xl border border-slate-200 dark:border-white/5 bg-white dark:bg-slate-900 p-8 shadow-sm text-center">
                <div class="mx-auto h-24 w-24 rounded-full bg-gradient-to-tr from-indigo-500 to-purple-500 flex items-center justify-center text-white text-3xl font-black mb-6 shadow-lg shadow-indigo-500/20">
                    <?= strtoupper(substr($username, 0, 1)) ?>
                </div>
                <h3 class="text-xl font-black text-slate-900 dark:text-white uppercase tracking-tighter mb-1"><?= htmlspecialchars($username) ?></h3>
                <p class="text-[10px] font-black uppercase tracking-widest text-indigo-600 mb-6">
                    <?php 
                        $level = sessionAccessLevel();
                        if ($level === ACCESS_LEVEL_SUPERADMIN) echo "Super Administrateur";
                        elseif ($level === ACCESS_LEVEL_ADMIN) echo "Administrateur";
                        else echo "Utilisateur";
                    ?>
                </p>
                <div class="space-y-4 border-t border-slate-100 dark:border-white/5 pt-6 text-left">
                    <div>
                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 mb-1">Rôle</p>
                        <p class="text-xs font-bold text-slate-700 dark:text-slate-300"><?= ucfirst($user['role']) ?></p>
                    </div>
                    <div>
                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 mb-1">Membre depuis</p>
                        <p class="text-xs font-bold text-slate-700 dark:text-slate-300"><?= date('d/m/Y', strtotime($user['created_at'])) ?></p>
                    </div>
                </div>

                <!-- Affichage du mot de passe actuel -->
                <div class="mt-6 p-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-100 dark:border-white/5 text-left">
                    <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 mb-2">Mot de passe actuel</p>
                    <div class="flex items-center justify-between bg-white dark:bg-slate-900 rounded-xl px-3 py-2 shadow-sm border border-slate-100 dark:border-white/5">
                        <span id="currentPwdText" class="text-xs font-mono font-bold text-slate-700 dark:text-slate-300">••••••••</span>
                        <button type="button" onclick="revealCurrentPwd('<?= addslashes($user['password_plain'] ?? '') ?>', this)" class="text-slate-400 hover:text-indigo-600 transition p-1">
                            <i class="fas fa-eye text-[10px]"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="mt-6 rounded-3xl border border-slate-200 dark:border-white/5 bg-indigo-600 p-8 text-white shadow-lg shadow-indigo-500/20">
                <h4 class="text-[10px] font-black uppercase tracking-widest opacity-80 mb-4">À Propos</h4>
                <p class="text-xs font-bold leading-relaxed">
                    Ce système de Gestion Électronique de Documents (GED) est conçu par Mandresy.A pour le DGT.
                </p>
            </div>
        </section>

        <!-- Formulaire de changement de mot de passe -->
        <section class="lg:col-span-2">
            <div class="rounded-3xl border border-slate-200 dark:border-white/5 bg-white dark:bg-slate-900 p-8 shadow-sm h-full">
                <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-8 flex items-center gap-2">
                    <i class="fas fa-lock"></i> Sécurité & Mot de passe
                </h3>
                
                <form method="post" class="space-y-8">
                    <div class="grid gap-6">
                        <div>
                            <label class="block text-[9px] font-black uppercase tracking-widest text-slate-400 mb-3">Ancien mot de passe</label>
                            <div class="relative group">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"><i class="fas fa-key text-xs"></i></span>
                                <input type="password" name="old_password" id="old_password" placeholder="••••••••" class="w-full pl-12 pr-12 py-4 rounded-2xl border-slate-200 bg-slate-50 dark:bg-slate-950 dark:border-white/10 text-sm font-bold focus:ring-2 focus:ring-indigo-500 transition" required>
                                <button type="button" onclick="togglePassword('old_password', this)" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-indigo-600 transition">
                                    <i class="fas fa-eye text-xs"></i>
                                </button>
                            </div>
                        </div>

                        <div class="grid md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-[9px] font-black uppercase tracking-widest text-slate-400 mb-3">Nouveau mot de passe</label>
                                <div class="relative group">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"><i class="fas fa-shield-alt text-xs"></i></span>
                                    <input type="password" name="new_password" id="new_password" placeholder="Min 4 caractères" class="w-full pl-12 pr-12 py-4 rounded-2xl border-slate-200 bg-slate-50 dark:bg-slate-950 dark:border-white/10 text-sm font-bold focus:ring-2 focus:ring-indigo-500 transition" required>
                                    <button type="button" onclick="togglePassword('new_password', this)" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-indigo-600 transition">
                                        <i class="fas fa-eye text-xs"></i>
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[9px] font-black uppercase tracking-widest text-slate-400 mb-3">Confirmer le nouveau</label>
                                <div class="relative group">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"><i class="fas fa-check-double text-xs"></i></span>
                                    <input type="password" name="confirm_password" id="confirm_password" placeholder="••••••••" class="w-full pl-12 pr-12 py-4 rounded-2xl border-slate-200 bg-slate-50 dark:bg-slate-950 dark:border-white/10 text-sm font-bold focus:ring-2 focus:ring-indigo-500 transition" required>
                                    <button type="button" onclick="togglePassword('confirm_password', this)" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-indigo-600 transition">
                                        <i class="fas fa-eye text-xs"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4">
                        <button type="submit" name="update_password" class="w-full md:w-auto px-10 rounded-2xl bg-slate-900 dark:bg-white text-white dark:text-slate-950 py-5 text-[10px] font-black uppercase tracking-widest hover:scale-[1.02] active:scale-[0.98] transition shadow-xl dark:shadow-white/5">
                            Mettre à jour le mot de passe
                        </button>
                    </div>
                </form>
            </div>
        </section>
    </div>
</main>

<script>
function revealCurrentPwd(plainText, btn) {
    const span = document.getElementById('currentPwdText');
    const icon = btn.querySelector('i');
    if (span.textContent === '••••••••') {
        span.textContent = plainText;
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        span.textContent = '••••••••';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

function togglePassword(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>

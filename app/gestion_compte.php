<?php
// gestion_compte.php - Gestion des comptes utilisateurs
require_once 'auth.php';
check_admin();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $username = trim($_POST['new_username']);
    $password = $_POST['new_password'];
    $role = $_POST['new_role'];

    if (empty($username) || empty($password) || !in_array($role, ['user', 'admin'], true)) {
        $error = 'Tous les champs sont requis et le rôle doit être valide.';
    } elseif (strlen($password) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères.';
    } else {
        if (createUser($username, $password, $role)) {
            $message = 'Utilisateur créé avec succès.';
        } else {
            $error = 'Erreur lors de la création de l\'utilisateur. Le nom d\'utilisateur existe peut-être déjà.';
        }
    }
}

$db = getDB();

// Migrer les mots de passe vides (de l'ancien compte créé)
try {
    $db->exec("UPDATE users SET password_plain = '(mot de passe non disponible)' WHERE password_plain IS NULL OR password_plain = ''");
} catch (Exception $e) {
}

$pageTitle = "Gestion des comptes - Administration";
$currentPage = 'gestion_compte';
$baseHref = '../';
require_once __DIR__ . '/../app/header.php';
?>

    <main class="mx-auto max-w-6xl px-4 py-12">
        <div class="mb-10 text-center">
            <h2 class="text-4xl font-black text-slate-900 dark:text-white tracking-tighter uppercase">Gestion des comptes</h2>
            <p class="mt-2 text-xs font-black uppercase tracking-[0.3em] text-indigo-600 dark:text-indigo-400">Administration des utilisateurs</p>
        </div>

        <?php if ($message): ?>
            <div class="mb-8 rounded-2xl border border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20 p-5 text-xs font-black uppercase tracking-widest flex items-center gap-4 text-emerald-600 dark:text-emerald-400 animate-in zoom-in duration-300">
                <i class="fas fa-check-circle text-xl"></i>
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mb-8 rounded-2xl border border-red-500 bg-red-50 dark:bg-red-900/20 p-5 text-xs font-black uppercase tracking-widest flex items-center gap-4 text-red-600 dark:text-red-400 animate-in zoom-in duration-300">
                <i class="fas fa-exclamation-circle text-xl"></i>
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <div class="grid gap-10 lg:grid-cols-1">
            <!-- Table des utilisateurs -->
            <section class="rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/5 p-8 shadow-xl overflow-hidden">
                <div class="mb-8 flex items-center justify-between border-b border-slate-100 dark:border-white/5 pb-6">
                    <div>
                        <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest">Utilisateurs existants</h3>
                        <p class="text-[9px] font-bold text-slate-400 dark:text-slate-500 mt-1 uppercase tracking-widest italic">Liste complète des accès</p>
                    </div>
                    <div class="flex gap-2">
                        <div class="h-1.5 w-1.5 rounded-full bg-indigo-500"></div>
                        <div class="h-1.5 w-1.5 rounded-full bg-slate-300 dark:bg-slate-700"></div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-[11px] font-bold uppercase tracking-tight">
                        <thead>
                            <tr class="border-b border-slate-100 dark:border-white/5 text-slate-400 dark:text-slate-500">
                                <th class="px-4 py-4 font-black uppercase tracking-widest">Nom d'utilisateur</th>
                                <th class="px-4 py-4 font-black uppercase tracking-widest">Mot de passe</th>
                                <th class="px-4 py-4 font-black uppercase tracking-widest">Rôle</th>
                                <th class="px-4 py-4 font-black uppercase tracking-widest">Création</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50 dark:divide-white/5">
                            <?php
                            $users = $db->query('SELECT username, password_plain, role, created_at FROM users ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($users as $user):
                                $pwd_display = !empty($user['password_plain']) ? htmlspecialchars($user['password_plain']) : '***';
                            ?>
                            <tr class="hover:bg-slate-50 dark:hover:bg-white/5 transition-colors">
                                <td class="px-4 py-5 text-slate-900 dark:text-white"><?php echo htmlspecialchars($user['username']); ?></td>
                                <td class="px-4 py-5 text-slate-500 font-mono"><?php echo $pwd_display; ?></td>
                                <td class="px-4 py-5">
                                    <span class="inline-block px-3 py-1 rounded-full text-[9px] font-black uppercase <?= $user['role'] === 'admin' ? 'bg-indigo-100 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' ?>">
                                        <?php echo htmlspecialchars($user['role']); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-5 text-slate-400"><?php echo htmlspecialchars($user['created_at']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Formulaire de création -->
            <section class="rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/5 p-8 shadow-xl">
                <div class="mb-8 flex items-center justify-between border-b border-slate-100 dark:border-white/5 pb-6">
                    <div>
                        <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest">Créer un utilisateur</h3>
                        <p class="text-[9px] font-bold text-slate-400 dark:text-slate-500 mt-1 uppercase tracking-widest italic">Nouveau compte système</p>
                    </div>
                </div>

                <form method="POST" action="gestion_compte.php" class="grid gap-8 md:grid-cols-3">
                    <div class="space-y-3">
                        <label for="new_username" class="text-[10px] font-black uppercase tracking-widest text-slate-400">Identifiant</label>
                        <input type="text" id="new_username" name="new_username" required
                               class="w-full rounded-2xl border-none bg-slate-50 dark:bg-slate-950 px-6 py-4 text-sm font-bold text-slate-900 dark:text-white shadow-inner focus:ring-4 focus:ring-indigo-600/10 transition">
                    </div>
                    <div class="space-y-3">
                        <label for="new_password" class="text-[10px] font-black uppercase tracking-widest text-slate-400">Mot de passe</label>
                        <input type="password" id="new_password" name="new_password" required
                               class="w-full rounded-2xl border-none bg-slate-50 dark:bg-slate-950 px-6 py-4 text-sm font-bold text-slate-900 dark:text-white shadow-inner focus:ring-4 focus:ring-indigo-600/10 transition">
                    </div>
                    <div class="space-y-3">
                        <label for="new_role" class="text-[10px] font-black uppercase tracking-widest text-slate-400">Niveau d'accès</label>
                        <select id="new_role" name="new_role" required
                                class="w-full rounded-2xl border-none bg-slate-50 dark:bg-slate-950 px-6 py-4 text-sm font-bold text-slate-900 dark:text-white shadow-inner focus:ring-4 focus:ring-indigo-600/10 transition appearance-none">
                            <option value="user">Utilisateur (Lecture)</option>
                            <option value="admin">Administrateur (Total)</option>
                        </select>
                    </div>
                    <div class="md:col-span-3 flex justify-end">
                        <button type="submit" name="create_user" class="rounded-2xl bg-indigo-600 px-10 py-5 text-[11px] font-black uppercase tracking-widest text-white shadow-lg shadow-indigo-600/20 hover:bg-indigo-700 hover:scale-[1.02] transition">
                            Ajouter au système
                        </button>
                    </div>
                </form>
            </section>
        </div>

        <div class="mt-12 text-center">
            <a href="<?= $baseHref ?>index.php" class="inline-flex items-center gap-3 px-8 py-4 rounded-2xl bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-white/5 text-[10px] font-black uppercase tracking-widest text-slate-600 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-white transition group">
                <i class="fas fa-arrow-left transition group-hover:-translate-x-1"></i> 
                Retour à l'accueil
            </a>
        </div>
    </main>

<?php require_once __DIR__ . '/../app/footer.php'; ?>
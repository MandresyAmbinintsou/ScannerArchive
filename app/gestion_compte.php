<?php
// gestion_compte.php - Gestion des comptes utilisateurs
require_once 'auth.php';
check_superadmin();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $username = trim($_POST['new_username']);
    $password = $_POST['new_password'];
    $role = (string)($_POST['new_role'] ?? '');

    if (empty($username) || empty($password) || !in_array($role, ['0', '1', '2', 'user', 'admin'], true)) {
        $error = 'Tous les champs sont requis et le niveau doit être valide.';
    } elseif (strlen($password) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères.';
    } else {
        if (createUser($username, $password, $role, true)) {
            $message = 'Utilisateur créé avec succès.';
        } else {
            $error = 'Erreur lors de la création de l\'utilisateur. Le nom d\'utilisateur existe peut-être déjà.';
        }
    }
}

$db = getDB();

// Approbation / refus
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_user_id'])) {
    $userId = (int)$_POST['approve_user_id'];
    try {
        $stmt = $db->prepare("UPDATE users SET is_approved = TRUE, approved_at = CURRENT_TIMESTAMP WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $message = "Compte approuvé.";
    } catch (Throwable $e) {
        $error = "Erreur approbation : " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_user_id'])) {
    $userId = (int)$_POST['reject_user_id'];
    try {
        // Refus = suppression du compte (plus simple pour l'admin).
        $stmt = $db->prepare("DELETE FROM users WHERE id = :id AND role <> 'admin'");
        $stmt->execute([':id' => $userId]);
        $message = "Compte refusé/supprimé.";
    } catch (Throwable $e) {
        $error = "Erreur refus : " . $e->getMessage();
    }
}

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
                    <table class="w-full text-left text-[11px] font-bold tracking-tight">
                        <thead>
                            <tr class="border-b border-slate-100 dark:border-white/5 text-slate-400 dark:text-slate-500">
                                <th class="px-4 py-4 font-black uppercase tracking-widest">Nom d'utilisateur</th>
                                <th class="px-4 py-4 font-black uppercase tracking-widest">Mot de passe</th>
                                <th class="px-4 py-4 font-black uppercase tracking-widest">Niveau</th>
                                <th class="px-4 py-4 font-black uppercase tracking-widest">Rôle</th>
                                <th class="px-4 py-4 font-black uppercase tracking-widest">Statut</th>
                                <th class="px-4 py-4 font-black uppercase tracking-widest">Création</th>
                                <th class="px-4 py-4 font-black uppercase tracking-widest">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50 dark:divide-white/5">
                            <?php
                            $users = $db->query('SELECT id, username, password_plain, role, access_level, is_approved, created_at FROM users ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($users as $user):
                                $pwd_display = !empty($user['password_plain']) ? htmlspecialchars($user['password_plain']) : '***';
                                $isApproved = (bool)($user['is_approved'] ?? true);
                                $level = isset($user['access_level']) ? (int)$user['access_level'] : ($user['role'] === 'admin' ? 0 : 2);
                            ?>
                            <tr class="user-row hover:bg-slate-50 dark:hover:bg-white/5 transition-colors cursor-pointer group" 
                                onclick="openAccountDrawer(<?= (int)$user['id'] ?>, '<?= addslashes(htmlspecialchars($user['username'])) ?>')">
                                <td class="px-4 py-5 text-slate-900 dark:text-white font-bold"><?php echo htmlspecialchars($user['username']); ?></td>
                                <td class="px-4 py-5 text-slate-500 font-mono"><?php echo $pwd_display; ?></td>
                                <td class="px-4 py-5">
                                    <span class="inline-block px-3 py-1 rounded-full text-[9px] font-black uppercase <?= $level === 0 ? 'bg-indigo-100 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400' : ($level === 1 ? 'bg-sky-100 text-sky-700 dark:bg-sky-900/20 dark:text-sky-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400') ?>">
                                        <?php echo 'N' . $level; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-5">
                                    <span class="inline-block px-3 py-1 rounded-full text-[9px] font-black uppercase <?= $user['role'] === 'admin' ? 'bg-indigo-100 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' ?>">
                                        <?php echo htmlspecialchars($user['role']); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-5">
                                    <span class="inline-block px-3 py-1 rounded-full text-[9px] font-black uppercase <?= $isApproved ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/20 dark:text-amber-400' ?>">
                                        <?= $isApproved ? 'Approuvé' : 'En attente' ?>
                                    </span>
                                </td>
                                <td class="px-4 py-5 text-slate-400"><?php echo htmlspecialchars($user['created_at']); ?></td>
                                <td class="px-4 py-5" onclick="event.stopPropagation()">
                                    <div class="flex items-center gap-2">
                                        <button type="button" onclick="openAccountDrawer(<?= (int)$user['id'] ?>, '<?= addslashes(htmlspecialchars($user['username'])) ?>')" 
                                                class="h-10 w-10 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-500 hover:bg-indigo-600 hover:text-white flex items-center justify-center transition" title="Gérer le compte">
                                            <i class="fas fa-user-cog"></i>
                                        </button>

                                        <?php if (!$isApproved): ?>
                                            <form method="POST" action="gestion_compte.php">
                                                <button type="submit" name="approve_user_id" value="<?= (int)$user['id'] ?>"
                                                        class="h-10 w-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-600 hover:text-white flex items-center justify-center transition"
                                                        title="Approuver">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($user['role'] !== 'admin' || $user['access_level'] != 0): ?>
                                            <form method="POST" action="gestion_compte.php" onsubmit="return confirm('Supprimer ce compte ?');">
                                                <button type="submit" name="reject_user_id" value="<?= (int)$user['id'] ?>"
                                                        class="h-10 w-10 rounded-xl bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 hover:bg-red-600 hover:text-white flex items-center justify-center transition"
                                                        title="Supprimer">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
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
                            <option value="2">Niveau 2 : User</option>
                            <option value="1">Niveau 1 : Admin (sans gestion comptes)</option>
                            <option value="0">Niveau 0 : Superadmin (accès total)</option>
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

    <!-- Account Modal -->
    <div id="accountModal" class="fixed inset-0 z-[100] invisible flex items-center justify-center p-4">
        <div id="modalOverlay" class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm opacity-0 transition-opacity duration-300 ease-in-out cursor-pointer"></div>
        <div id="modalContent" class="relative w-full max-w-5xl bg-slate-50 dark:bg-slate-950 shadow-2xl scale-95 opacity-0 transition-all duration-300 ease-out border border-white/5 rounded-[2.5rem] overflow-hidden flex flex-col max-h-[90vh]">
            <div class="p-6 border-b border-slate-200 dark:border-white/5 flex items-center justify-between bg-white dark:bg-slate-900">
                <div class="flex items-center gap-4">
                    <div class="h-10 w-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center shadow-lg shadow-indigo-600/20">
                        <i class="fas fa-user-edit"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest" id="modalUsername">Nom utilisateur</h3>
                        <p class="text-[9px] font-bold text-indigo-600 mt-0.5 uppercase tracking-widest">Configuration du compte</p>
                    </div>
                </div>
                <button onclick="closeAccountModal()" class="h-10 w-10 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-500 hover:bg-red-500 hover:text-white transition flex items-center justify-center">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-950">
                <iframe id="accountIframe" src="about:blank" class="w-full h-[600px] border-none"></iframe>
            </div>
        </div>
    </div>

    <script>
        function openAccountDrawer(userId, username) { // Keep name for compatibility with existing onclicks or rename both
            openAccountModal(userId, username);
        }

        function openAccountModal(userId, username) {
            const modal = document.getElementById('accountModal');
            const overlay = document.getElementById('modalOverlay');
            const content = document.getElementById('modalContent');
            const iframe = document.getElementById('accountIframe');
            const title = document.getElementById('modalUsername');

            title.textContent = username;
            iframe.src = `account.php?id=${userId}&iframe=1`;

            modal.classList.remove('invisible');
            setTimeout(() => {
                overlay.classList.add('opacity-100');
                content.classList.remove('scale-95', 'opacity-0');
                content.classList.add('scale-100', 'opacity-100');
            }, 10);
            
            document.body.style.overflow = 'hidden';
        }

        function closeAccountModal() {
            const modal = document.getElementById('accountModal');
            const overlay = document.getElementById('modalOverlay');
            const content = document.getElementById('modalContent');
            const iframe = document.getElementById('accountIframe');

            overlay.classList.remove('opacity-100');
            content.classList.remove('scale-100', 'opacity-100');
            content.classList.add('scale-95', 'opacity-0');
            
            setTimeout(() => {
                modal.classList.add('invisible');
                iframe.src = 'about:blank';
                document.body.style.overflow = '';
            }, 300);
        }

        document.getElementById('modalOverlay').onclick = closeAccountModal;
        
        // Listen for escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeAccountModal();
        });
    </script>

<?php require_once __DIR__ . '/../app/footer.php'; ?>

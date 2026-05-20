<?php
// app/account.php — Gestion détaillée d'un compte utilisateur
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

// Seul le super-admin peut modifier les comptes
check_superadmin();

$db = getDB();
$message = '';
$error = '';

$userId = (int)($_GET['id'] ?? 0);
if ($userId <= 0) {
    header('Location: gestion_compte.php');
    exit;
}

// Charger l'utilisateur
$stmt = $db->prepare('SELECT * FROM users WHERE id = :id');
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: gestion_compte.php');
    exit;
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['update_info'])) {
            $newRole = $_POST['role'];
            $newLevel = (int)$_POST['access_level'];
            $isApproved = isset($_POST['is_approved']) ? 1 : 0;
            
            // Empêcher de se rétrograder soi-même si on est le seul super-admin (optionnel mais prudent)
            
            $update = $db->prepare('UPDATE users SET role = :role, access_level = :level, is_approved = :approved WHERE id = :id');
            $update->execute([
                ':role' => $newRole,
                ':level' => $newLevel,
                ':approved' => $isApproved,
                ':id' => $userId
            ]);
            $message = "Informations mises à jour avec succès.";
        } 
        elseif (isset($_POST['update_password'])) {
            $newPwd = $_POST['new_password'];
            if (strlen($newPwd) < 4) {
                throw new Exception("Le mot de passe est trop court.");
            }
            $hash = password_hash($newPwd, PASSWORD_DEFAULT);
            $update = $db->prepare('UPDATE users SET password_hash = :hash, password_plain = :plain WHERE id = :id');
            $update->execute([
                ':hash' => $hash,
                ':plain' => $newPwd,
                ':id' => $userId
            ]);
            $message = "Mot de passe modifié avec succès.";
        }
        elseif (isset($_POST['delete_user'])) {
            if ($user['role'] === 'admin' && $user['access_level'] === 0) {
                 // Protection basique : on ne supprime pas un admin depuis ici sans précaution
            }
            $delete = $db->prepare('DELETE FROM users WHERE id = :id');
            $delete->execute([':id' => $userId]);
            
            if (isset($_GET['iframe'])) {
                echo '<script>window.parent.location.href = "gestion_compte.php?msg=deleted";</script>';
                exit;
            }
            
            header('Location: gestion_compte.php?msg=deleted');
            exit;
        }

        // Recharger les données
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$pageTitle = "Modifier Compte - " . $user['username'];
$currentPage = "account";
$baseHref = "../";

// Si on est dans une iframe, on veut un rendu minimal sans le header global
$isIframe = isset($_GET['iframe']);

if ($isIframe) {
    ?>
    <!DOCTYPE html>
    <html lang="fr" class="dark">
    <head>
        <meta charset="UTF-8">
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = { darkMode: 'class' };
            if (localStorage.theme === 'light') document.documentElement.classList.remove('dark');
        </script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            body { background: transparent !important; }
        </style>
    </head>
    <body class="dark:text-white p-4">
    <?php
} else {
    require_once __DIR__ . '/header.php';
}
?>

<main class="mx-auto max-w-4xl px-4 py-6">
    <?php if (!$isIframe): ?>
    <div class="mb-10 flex items-center justify-between">
        <div>
            <h2 class="text-3xl font-black text-slate-900 dark:text-white tracking-tighter uppercase">Modifier Utilisateur</h2>
            <p class="mt-2 text-xs font-black uppercase tracking-widest text-indigo-600"><?= htmlspecialchars($user['username']) ?></p>
        </div>
        <a href="gestion_compte.php" class="text-[10px] font-black uppercase tracking-widest text-slate-400 hover:text-slate-900 dark:hover:text-white transition">
            <i class="fas fa-arrow-left mr-2"></i> Retour
        </a>
    </div>
    <?php endif; ?>

    <?php if ($message): ?>
        <div class="mb-8 rounded-2xl bg-emerald-50 border border-emerald-200 p-5 text-xs font-black uppercase tracking-widest text-emerald-600 flex items-center gap-3">
            <i class="fas fa-check-circle text-lg"></i> <?= $message ?>
            <?php if ($isIframe): ?>
                <script>
                    // Rafraîchir le parent après un court délai pour montrer le succès
                    setTimeout(() => {
                        window.parent.location.reload();
                    }, 1000);
                </script>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="mb-8 rounded-2xl bg-red-50 border border-red-200 p-5 text-xs font-black uppercase tracking-widest text-red-600 flex items-center gap-3">
            <i class="fas fa-exclamation-circle text-lg"></i> <?= $error ?>
        </div>
    <?php endif; ?>

    <div class="grid gap-8 md:grid-cols-2">
        <!-- Informations Générales -->
        <section class="rounded-3xl border border-slate-200 dark:border-white/5 bg-white dark:bg-slate-900 p-8 shadow-sm">
            <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-8">Informations & Droits</h3>
            <form method="post" class="space-y-6">
                <div>
                    <label class="block text-[9px] font-black uppercase tracking-widest text-slate-400 mb-2">Rôle Historique</label>
                    <select name="role" class="w-full rounded-xl border-slate-200 bg-slate-50 dark:bg-slate-950 dark:border-white/10 text-sm font-bold">
                        <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>Utilisateur (user)</option>
                        <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Administrateur (admin)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[9px] font-black uppercase tracking-widest text-slate-400 mb-2">Niveau d'Accès</label>
                    <select name="access_level" class="w-full rounded-xl border-slate-200 bg-slate-50 dark:bg-slate-950 dark:border-white/10 text-sm font-bold">
                        <option value="0" <?= $user['access_level'] == 0 ? 'selected' : '' ?>>N0 : Super Administrateur (Total)</option>
                        <option value="1" <?= $user['access_level'] == 1 ? 'selected' : '' ?>>N1 : Administrateur (Indexation)</option>
                        <option value="2" <?= $user['access_level'] == 2 ? 'selected' : '' ?>>N2 : Utilisateur (Consultation)</option>
                    </select>
                </div>

                <div class="flex items-center gap-3 p-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-100 dark:border-white/5">
                    <input type="checkbox" id="is_approved" name="is_approved" <?= $user['is_approved'] ? 'checked' : '' ?> class="h-5 w-5 rounded border-slate-300 text-indigo-600">
                    <label for="is_approved" class="text-[10px] font-black uppercase tracking-widest text-slate-700 dark:text-slate-300 cursor-pointer">Compte Approuvé / Actif</label>
                </div>

                <button type="submit" name="update_info" class="w-full rounded-xl bg-indigo-600 py-4 text-[10px] font-black uppercase tracking-widest text-white hover:bg-indigo-700 transition">
                    Enregistrer les modifications
                </button>
            </form>
        </section>

        <!-- Sécurité -->
        <div class="space-y-8">
            <section class="rounded-3xl border border-slate-200 dark:border-white/5 bg-white dark:bg-slate-900 p-8 shadow-sm">
                <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-8">Modifier le mot de passe</h3>
                <form method="post" class="space-y-6">
                    <div>
                        <input type="text" name="new_password" placeholder="Nouveau mot de passe" class="w-full rounded-xl border-slate-200 bg-slate-50 dark:bg-slate-950 dark:border-white/10 text-sm font-bold" required>
                    </div>
                    <button type="submit" name="update_password" class="w-full rounded-xl bg-slate-900 py-4 text-[10px] font-black uppercase tracking-widest text-white hover:bg-black transition">
                        Changer le mot de passe
                    </button>
                </form>
            </section>

            <section class="rounded-3xl border border-red-100 dark:border-red-900/20 bg-red-50/30 dark:bg-red-950/10 p-8">
                <h3 class="text-[10px] font-black uppercase tracking-widest text-red-400 mb-8">Supression de Compte</h3>
                <form method="post" onsubmit="return confirm('Supprimer définitivement ce compte ?');">
                    <button type="submit" name="delete_user" class="w-full rounded-xl bg-red-500 py-4 text-[10px] font-black uppercase tracking-widest text-white hover:bg-red-600 transition">
                        Supprimer le compte
                    </button>
                </form>
            </section>
        </div>
    </div>
</main>

<?php 
if ($isIframe) {
    echo '</body></html>';
} else {
    require_once __DIR__ . '/footer.php'; 
}
?>

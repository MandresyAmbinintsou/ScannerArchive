<?php
// gestion_compte.php - Gestion des comptes utilisateurs
require_once 'auth.php';
check_admin();
require_once 'config/database.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $username = trim($_POST['new_username']);
    $password = $_POST['new_password'];
    $role = $_POST['new_role'];

    if (empty($username) || empty($password) || !in_array($role, ['user', 'admin'])) {
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

$db = Database::getInstance();

// Migrer les mots de passe vides (de l'ancien compte créé)
try {
    $db->exec("UPDATE users SET password_plain = '(mot de passe non disponible)' WHERE password_plain IS NULL OR password_plain = ''");
} catch (Exception $e) {
}

$pageTitle = "Gestion des comptes - Administration";
$currentPage = 'gestion_compte';
include 'templates/header.php';
?>

    <style>
        h1 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-weight: 300;
            border-bottom: 2px solid #e74c3c;
            padding-bottom: 10px;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }

        .form-group input[type="text"],
        .form-group input[type="password"],
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        .form-group input[type="text"]:focus,
        .form-group input[type="password"]:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3498db;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }

        .btn-success {
            background: #27ae60;
            color: white;
        }

        .btn-success:hover {
            background: #219a52;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }

        tr:nth-child(even) {
            background: #f9f9fa;
        }

        tr:hover {
            background: #f1f1f1;
        }
    </style>

    <h1>Gestion des comptes utilisateurs</h1>

    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-title">Utilisateurs existants</div>
        <table>
            <thead>
                <tr>
                    <th>Nom d'utilisateur</th>
                    <th>Mot de passe</th>
                    <th>Rôle</th>
                    <th>Date de création</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $users = $db->query('SELECT username, password_plain, role, created_at FROM users ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
                foreach ($users as $user):
                    $pwd_display = !empty($user['password_plain']) ? htmlspecialchars($user['password_plain']) : '***';
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo $pwd_display; ?></td>
                    <td><?php echo htmlspecialchars($user['role']); ?></td>
                    <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <div class="card-title">Créer un nouvel utilisateur</div>
        <form method="POST" action="gestion_compte.php">
            <div class="form-group">
                <label for="new_username">Nom d'utilisateur :</label>
                <input type="text" id="new_username" name="new_username" required>
            </div>
            <div class="form-group">
                <label for="new_password">Mot de passe :</label>
                <input type="password" id="new_password" name="new_password" required>
            </div>
            <div class="form-group">
                <label for="new_role">Rôle :</label>
                <select id="new_role" name="new_role" required>
                    <option value="user">Utilisateur</option>
                    <option value="admin">Administrateur</option>
                </select>
            </div>
            <button type="submit" name="create_user" class="btn btn-success">Créer l'utilisateur</button>
        </form>
    </div>

    <div style="text-align: center; margin-top: 20px;">
        <a href="admin.php" class="btn btn-primary">Retour à l'administration</a>
    </div>

<?php include 'templates/footer.php'; ?>
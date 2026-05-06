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
        $error = "Identifiants incorrects";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion - Gestion d'Archives</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f7f6; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-card { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        h2 { text-align: center; color: #2c3e50; margin-bottom: 30px; border-bottom: 2px solid #e74c3c; padding-bottom: 10px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; color: #7f8c8d; }
        input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: #3498db; border: none; color: white; border-radius: 5px; cursor: pointer; font-size: 16px; transition: background 0.3s; }
        button:hover { background: #2980b9; }
        .error { color: #e74c3c; text-align: center; margin-bottom: 20px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="login-card">
        <h2>GED-MEF</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Utilisateur</label>
                <input type="text" name="username" required autofocus>
            </div>
            <div class="form-group">
                <label>Mot de passe</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit">Se connecter</button>
        </form>
        <p style="margin-top: 20px; color: #7f8c8d; font-size: 14px; text-align: center;">
            Pas de compte ? <a href="formulaire.php" style="color: #3498db; text-decoration: none;">Créer un compte</a>
        </p>
    </div>
</body>
</html>

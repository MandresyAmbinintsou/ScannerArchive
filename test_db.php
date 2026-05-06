<?php
require_once __DIR__ . '/config/database.php';

echo "--- Test de connexion PostgreSQL ---\n";
echo "Host: " . DB_HOST . "\n";
echo "Port: " . DB_PORT . "\n";
echo "Database: " . DB_NAME . "\n";
echo "User: " . DB_USER . "\n";
echo "Password: " . (DB_PASS === '' ? '(vide)' : '********') . "\n";
echo "------------------------------------\n";

try {
    $pdo = getDB();
    echo "✅ Connexion réussie !\n";
    
    // Vérifier si les tables existent
    $stmt = $pdo->query("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname = 'public'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tables)) {
        echo "⚠️  Attention : La base de données est vide (aucune table trouvée).\n";
        echo "👉 Action : Importez le schéma avec : psql -U ".DB_USER." -d ".DB_NAME." -f scripts/schema.pg.sql\n";
    } else {
        echo "Tables trouvées : " . implode(', ', $tables) . "\n";
    }

} catch (PDOException $e) {
    echo "❌ Erreur de connexion : " . $e->getMessage() . "\n";
    
    if (strpos($e->getMessage(), 'password authentication failed') !== false) {
        echo "👉 Le mot de passe pour l'utilisateur '" . DB_USER . "' est incorrect ou manquant.\n";
    } elseif (strpos($e->getMessage(), 'does not exist') !== false) {
        echo "👉 La base de données '" . DB_NAME . "' n'existe pas.\n";
    }
}

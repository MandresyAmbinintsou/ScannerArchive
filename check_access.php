<?php
echo "<h1>Diagnostic d'accès GED-MEF</h1>";
echo "<p>Votre IP actuelle : <b>" . $_SERVER['REMOTE_ADDR'] . "</b></p>";
echo "<p>Nom du serveur : <b>" . $_SERVER['SERVER_NAME'] . "</b></p>";
echo "<p>Dossier du projet : <b>" . __DIR__ . "</b></p>";

echo "<h2>Tests de permissions :</h2>";
if (is_readable('index.php')) {
    echo "<p style='color:green'>[OK] index.php est lisible par Apache.</p>";
} else {
    echo "<p style='color:red'>[ERREUR] index.php n'est pas lisible. Vérifiez les droits NTFS.</p>";
}

if (file_exists('.htaccess')) {
    echo "<p style='color:green'>[OK] Fichier .htaccess détecté.</p>";
} else {
    echo "<p style='color:orange'>[INFO] Fichier .htaccess absent à la racine.</p>";
}

echo "<h2>Conseils pour le PC2 :</h2>";
echo "<ul>";
echo "<li>Si vous voyez cette page depuis le PC2, alors le 403 est réglé !</li>";
echo "<li>Si vous voyez 403 sur index.php mais pas ici, c'est un problème de configuration de DirectoryIndex ou de Require dans Apache.</li>";
echo "</ul>";

# Utilisation de Ngrok pour l'accès à distance

Si l'accès direct via IP locale (ex: `192.168.x.x`) renvoie une erreur **403 Forbidden** ou est bloqué par le pare-feu de Windows 8, **Ngrok** est la solution la plus simple pour créer un tunnel sécurisé.

## 1. Installation sur PC1 (Windows 8)

1.  Téléchargez Ngrok pour Windows : [https://ngrok.com/download](https://ngrok.com/download)
2.  Extrayez le fichier `ngrok.exe` dans la racine du projet `GED-MEF` (ou dans un dossier de votre choix).
3.  Créez un compte gratuit sur Ngrok pour obtenir votre **Authtoken**.
4.  Ouvrez un terminal (CMD ou PowerShell) dans le dossier où se trouve `ngrok.exe`.
5.  Configurez votre jeton (une seule fois) :
    ```cmd
    ngrok config add-authtoken VOTRE_JETON_ICI
    ```

## 2. Lancer le tunnel

Selon la manière dont vous lancez le projet :

### Option A : Si vous utilisez Apache (XAMPP/WAMP) sur le port 80
```cmd
ngrok http 80
```

### Option B : Si vous utilisez le serveur PHP intégré (port 8000)
1. Lancez d'abord le projet avec `portable\windows\run.bat` (ou `php -S 0.0.0.0:8000`).
2. Dans un autre terminal, lancez ngrok :
   ```cmd
   ngrok http 8000
   ```

## 3. Accès depuis PC2

Une fois Ngrok lancé, il affichera une URL de type :
`https://a1b2-c3d4.ngrok-free.app`

Copiez cette URL et collez-la dans le navigateur de **PC2**.

---

## Pourquoi l'erreur "Forbidden" persiste-t-elle en local ?

L'erreur 403 survient généralement car :
1. **Apache** refuse les connexions qui ne viennent pas de `127.0.0.1`.
2. **Le Pare-feu Windows** bloque le port 80/8000.

**Note sur Ngrok :** Ngrok contourne le pare-feu car il crée une connexion *sortante* vers ses serveurs, que PC2 rejoint ensuite. C'est idéal pour Windows 8 qui a souvent des règles de sécurité strictes.

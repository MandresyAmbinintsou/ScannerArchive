# Mode d'emploi — Archive Viewer

## Objectif

Ce projet combine :
- interface web PHP / Tailwind
- base PostgreSQL
- moteur de scan PHP fallback
- moteur de scan Go rapide (`scannerfs` / `scannerfs.exe`)

Le moteur Go est compilé une seule fois pour Windows, puis il peut être copié sur d'autres PC Windows sans recompilation.

## Installation

1. Assurez-vous d'avoir PHP 8+ et PostgreSQL installés.
2. Placez le projet sur le serveur web ou ouvrez-le dans un environnement local.
3. Configurez PostgreSQL en mode `trust` ou avec variables d'environnement :
   - `DB_HOST`
   - `DB_PORT`
   - `DB_NAME` (par défaut `archive_db`)
   - `DB_USER` (`postgres` recommandé sur Windows)
   - `DB_PASS`

4. Le fichier `config/database.php` crée automatiquement la base si elle n'existe pas et exécute `scripts/schema.pg.sql`.

> Sur Windows, si l'auto-création de `archive_db` ne fonctionne pas, créez la base manuellement avec :
> `psql -U postgres -c "CREATE DATABASE archive_db"`

## Binaire Go Windows

- Le binaire Windows est disponible dans `bin/scannerfs.exe`.
- Vous pouvez exécuter ce binaire sur un PC Windows x86-64 sans avoir besoin de recompiler le projet.
- Si `app/indexer.php` demande `Go`, le code PHP va chercher automatiquement :
  - `GO_SCANNERFS_PATH` (variable d'environnement)
  - `bin/scannerfs.exe`
  - `bin/scannerfs`
  - `scannerfs.exe`
  - `scannerfs`

## Génération du binaire multi-plateforme

Si vous souhaitez reconstruire les binaires Go :

```bash
cd /chemin/vers/projet
chmod +x scripts/build_scannerfs.sh
./scripts/build_scannerfs.sh
```

Le script construit :
- `dist/scannerfs-linux-amd64`
- `dist/scannerfs-linux-arm64`
- `dist/scannerfs-windows-amd64.exe`
- `dist/scannerfs-darwin-amd64`
- `dist/scannerfs-darwin-arm64`

## Utilisation Web

1. Ouvrez `app/indexer.php` dans votre navigateur.
2. Choisissez le dossier racine de l’archive à scanner.
3. Sélectionnez le moteur :
   - `Moteur PHP` : stable et compatible partout
   - `Moteur Go` : plus rapide et plus robuste pour de grands ensembles de fichiers
4. Lancez l’indexation.

### Comportement intelligent

- Si le moteur Go est sélectionné mais que le binaire n’est pas disponible, le projet bascule automatiquement sur le moteur PHP.
- Cela garantit que l’indexation fonctionne même sur une machine où `scannerfs.exe` est absent.

## Vitesse d'indexation

### PHP fallback

- Le scanner PHP est plus simple et fonctionne sur toutes les plateformes où PHP est installé.
- Il est idéal comme solution universelle ou pour des déploiements rapides.
- Il calcule la durée et affiche un résumé dans le flux de sortie.

### Go Scanner

- Le scanner Go est conçu pour être plus rapide en exploitant le système de fichiers et le parallélisme.
- Il émet des événements JSONL et laisse à PHP le soin d'ingérer les données en base.
- Sur de grands volumes, Go est généralement plus rapide que le scanner PHP, surtout sur des SSD et des machines multi-coeurs.

## Postgresql portable

- Le projet fonctionne avec PostgreSQL local en mode `trust`, sans mot de passe pour l'accès local.
- Pour un environnement portable, configurez PostgreSQL de sorte que l'utilisateur système accède sans mot de passe.
- Le schéma est géré dans `scripts/schema.pg.sql` et inclut désormais la table `users` pour l’authentification.

## Authentification et compatibilité

- Les pages `app/login.php`, `app/formulaire.php` et `app/gestion_compte.php` sont intégrées à la base.
- La première création de compte devient administrateur.
- `app/header.php` affiche la connexion/inscription quand l'utilisateur n'est pas connecté.

## Conseils Windows

- Copiez `bin/scannerfs.exe` sur le PC Windows de destination.
- Faites pointer `GO_SCANNERFS_PATH` vers le binaire si nécessaire.
- Assurez-vous que le serveur PHP peut exécuter des binaires externes avec `proc_open()`.
- Si vous exécutez le projet via un serveur web Windows, utilisez un chemin Windows propre dans le champ racine.

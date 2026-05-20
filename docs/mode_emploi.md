# Mode d'emploi — Archive Viewer

## Objectif

Ce projet combine :
- interface web PHP / Tailwind
- base PostgreSQL
- moteur de scan PHP fallback
- moteur de scan Go rapide (`scannerfs` / `scannerfs.exe`)

Le moteur Go est compilé une seule fois pour Windows et peut être copié sur d'autres PC sans recompilation.

## Installation

1. Assurez-vous d'avoir PHP 8+ et PostgreSQL installés.
2. Placez le projet sur le serveur web ou dans un environnement local.
3. Configurez PostgreSQL en mode `trust` ou avec variables d'environnement :
   - `DB_HOST`
   - `DB_PORT`
   - `DB_NAME` (par défaut `archive_db`)
   - `DB_USER` (`postgres` recommandé sur Windows)
   - `DB_PASS`

4. Le fichier `config/database.php` crée automatiquement la base si elle n'existe pas et exécute `scripts/schema.pg.sql`.

> Sur Windows, si l'auto-création de `archive_db` ne fonctionne pas, créez la base manuellement :
> `psql -U postgres -c "CREATE DATABASE archive_db"`

## Binaire Go Windows

- Le binaire Windows se trouve dans `bin/scannerfs.exe`.
- Il peut être exécuté sur un PC Windows x86-64 sans recompilation.
- Si `app/indexer.php` sélectionne Go, le code PHP recherche automatiquement :
  - `GO_SCANNERFS_PATH`
  - `bin/scannerfs.exe`
  - `bin/scannerfs`
  - `scannerfs.exe`
  - `scannerfs`

## Génération du binaire multi-plateforme

```bash
cd /chemin/vers/projet
chmod +x scripts/build_scannerfs.sh
./scripts/build_scannerfs.sh
```

Le script génère :
- `dist/scannerfs-linux-amd64`
- `dist/scannerfs-linux-arm64`
- `dist/scannerfs-windows-amd64.exe`
- `dist/scannerfs-darwin-amd64`
- `dist/scannerfs-darwin-arm64`

## Utilisation Web

1. Ouvrez `app/indexer.php`.
2. Choisissez le dossier racine de l’archive.
3. Sélectionnez le moteur :
   - `Moteur PHP` : compatible partout
   - `Moteur Go` : plus rapide pour de grands volumes
4. Lancez l’indexation.

### Comportement intelligent

- Si le moteur Go est demandé mais absent, l’application bascule automatiquement sur PHP.
- Le fallback garantit l’indexation même sans `scannerfs.exe`.

## Vitesse réelle du scan

### PHP fallback

- `app/scan.php` mesure le temps avec `microtime(true)`.
- La sortie affiche `Durée : Xs`.

### Go Scanner

- `scan_go.php` exécute le binaire Go et lit le flux JSONL.
- Le résumé Go contient `duration_ms`.
- Le scan Go est optimisé pour le filesystem et le parallélisme.

### Résultat attendu

- Sur des SSD modernes, Go est souvent **2x à 5x plus rapide** que le scan PHP.
- Sur de petits volumes ou des disques lents, le gain est plus modéré.

## PostgreSQL portable

- Le projet supporte PostgreSQL local en mode `trust`, sans mot de passe pour l’accès local.
- Configurez PostgreSQL pour autoriser l’accès local sans mot de passe.
- Le schéma est géré par `scripts/schema.pg.sql`.

## Authentification et compatibilité

- `app/login.php`, `app/formulaire.php` et `app/gestion_compte.php` utilisent la base.
- Le premier compte créé est administrateur.
- `app/header.php` affiche les liens selon l’état de connexion.

## Conseils Windows

- Copiez `bin/scannerfs.exe` sur le PC Windows de destination.
- Définissez `GO_SCANNERFS_PATH` vers le binaire si besoin.
- Assurez-vous que PHP peut exécuter des binaires externes avec `proc_open()`.
- Sur un serveur web Windows, utilisez un chemin Windows propre pour la racine.

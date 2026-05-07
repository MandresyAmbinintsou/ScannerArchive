# Mode d’emploi — Archive Viewer (Web + DB + Go)

## 1) Point important: “un seul .exe pour tous les PC”

Un binaire **Windows (.exe)** ne peut pas tourner sur Linux/macOS, et inversement.
Ce qu’on peut faire en pratique:
- compiler **une fois par plateforme** et distribuer les binaires (`dist/scannerfs-windows-amd64.exe`, `dist/scannerfs-linux-amd64`, etc.)
- ou garder uniquement le **scanner PHP** (fallback) qui marche partout

Le projet implémente donc:
- **Fallback PHP** toujours disponible
- **Scanner Go** optionnel si le binaire correspondant au PC existe

## 2) Base de données: PostgreSQL

La connexion DB se configure via variables d’environnement (utilisées par `config/database.php`).

### PostgreSQL (portable via Docker)
Démarrer PostgreSQL en local (sans mot de passe, mode `trust`):
```bash
docker compose up -d
```

Puis configurer PHP:
```bash
export DB_HOST=127.0.0.1
export DB_PORT=5432
export DB_NAME=archive_db
export DB_USER=postgres
export DB_PASS=""
```

> Sous Windows, définissez explicitement `DB_USER=postgres` si votre compte système n'existe pas comme rôle PostgreSQL.

Schéma:
```bash
psql -U postgres -d archive_db -f scripts/schema.pg.sql
```

> Si la base `archive_db` n'existe pas encore et que l'auto-création échoue, créez-la manuellement :
> ```bash
> psql -U postgres -c "CREATE DATABASE archive_db"
> ```

## 3) Dossier racine de l’archive

Définir un dossier par défaut (optionnel):
```bash
export ARCHIVE_ROOT="/chemin/vers/archives"
```

Sinon tu peux saisir un chemin dans l’UI d’indexation.

## 4) Lancer le serveur web

```bash
php -S 127.0.0.1:8000
```

- UI principale: `http://127.0.0.1:8000/index.php`
- Indexation: `http://127.0.0.1:8000/app/indexer.php`

## 5) Choisir le moteur d’indexation (UI)

Dans `app/indexer.php`:
- **Scanner PHP (fallback)**: ne dépend d’aucun binaire externe
- **Scanner Go (rapide)**: nécessite le binaire `bin/scannerfs`

## 6) Compiler le scanner Go (filesystem-only)

### Build local (Linux)
```bash
GOCACHE=/tmp/gocache go build -buildvcs=false -o bin/scannerfs ./cmd/scannerfs
```

### Build multi-plateforme (release)
```bash
./scripts/build_scannerfs.sh
```
Les binaires sortent dans `dist/`.

Optionnel: tu peux forcer le chemin du binaire dans PHP:
```bash
export GO_SCANNERFS_PATH="/chemin/vers/scannerfs"
```

## 7) Robustesse / gros volumes

Recommandations:
- SSD si possible
- éviter de scanner via réseau lent (partages)
- pour Go: ajuster `--workers` si besoin (par défaut = CPU)

CLI de test:
```bash
./bin/scannerfs --root "/chemin/vers/archives" --emit-images
```

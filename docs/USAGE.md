# Mode d’emploi — Archive Viewer (Web + DB + Go)

![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white)
![Go](https://img.shields.io/badge/Go-00ADD8?logo=go&logoColor=white)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-336791?logo=postgresql&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-38B2AC?logo=tailwindcss&logoColor=white)

## 1) Point important : un binaire par plateforme

Un binaire **Windows (.exe)** ne peut pas tourner sur Linux/macOS, et inversement.
Ce projet propose deux modes :
- **Scanner PHP** : toujours disponible, compatible partout.
- **Scanner Go** : rapide quand le binaire correspondant existe.

## 2) Base de données : PostgreSQL

La connexion DB se configure via variables d’environnement dans `config/database.php`.

### PostgreSQL local ou Docker

```bash
docker compose up -d
```

Puis définir :

```bash
export DB_HOST=127.0.0.1
export DB_PORT=5432
export DB_NAME=archive_db
export DB_USER=postgres
export DB_PASS=""
```

> Sur Windows, définissez toujours `DB_USER=postgres` si votre compte système n'existe pas comme rôle PostgreSQL.

Initialiser le schéma :

```bash
psql -U postgres -d archive_db -f scripts/schema.pg.sql
```

Si la DB n’existe pas :

```bash
psql -U postgres -c "CREATE DATABASE archive_db"
```

## 3) Dossier racine de l’archive

Définir un dossier par défaut :

```bash
export ARCHIVE_ROOT="/chemin/vers/archives"
```

Ou saisir le chemin depuis l’interface `app/indexer.php`.

## 4) Lancer le serveur web

```bash
php -S 127.0.0.1:8000
```

Accès :
- `http://127.0.0.1:8000/index.php`
- `http://127.0.0.1:8000/app/indexer.php`

## 5) Choisir le moteur d’indexation

Dans `app/indexer.php` :
- **Scanner PHP (fallback)** : compatible sans binaire externe.
- **Scanner Go** : plus rapide, nécessite `bin/scannerfs` ou `bin/scannerfs.exe`.

## 6) Temps réel : vitesse de scan Go vs PHP

### Vitesse PHP

- `app/scan.php` calcule le temps de scan via `microtime(true)`.
- Il affiche `Durée : Xs` à la fin.

### Vitesse Go

- `scan_go.php` exécute `cmd/scannerfs` et mesure le temps total du pipeline.
- Le binaire Go fournit un résumé au format JSON avec `duration_ms`.

### Mesure réelle

- Lançez le scan avec le même répertoire sur les deux moteurs.
- Comparez les sorties `Durée : Xs`.
- Sur SSD et multi-coeurs, Go est souvent **2x à 5x plus rapide**.

### Exemple Go

```json
{
  "type": "summary",
  "summary": {
    "duration_ms": 12345,
    "matricules": 120,
    "sous_dossiers": 480,
    "images": 10240
  }
}
```

## 7) Compiler le scanner Go

### Build local (Linux)

```bash
GOCACHE=/tmp/gocache go build -buildvcs=false -o bin/scannerfs ./cmd/scannerfs
```

### Build multi-plateforme

```bash
./scripts/build_scannerfs.sh
```

Les binaires sont générés dans `dist/`.

## 8) Robustesse / gros volumes

Recommandations :
- utilisez un **SSD** quand possible
- évitez les partages réseau lents
- vérifiez la latence PostgreSQL
- pour Go, augmentez `--workers` si besoin

## 9) Test du scanner Go

```bash
./bin/scannerfs --root "/chemin/vers/archives" --emit-images
```

## 10) Option Windows

Copiez `bin/scannerfs.exe` dans le dossier Windows et définissez `GO_SCANNERFS_PATH` si besoin.

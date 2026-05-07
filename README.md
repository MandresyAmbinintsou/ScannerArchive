# GED-MEF — Archive Viewer

![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white)
![Go](https://img.shields.io/badge/Go-00ADD8?logo=go&logoColor=white)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-336791?logo=postgresql&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-38B2AC?logo=tailwindcss&logoColor=white)
![Windows](https://img.shields.io/badge/Windows-0078D6?logo=windows&logoColor=white)
![Workerman](https://img.shields.io/badge/Workerman-PHP-FC69B3?logo=php&logoColor=white)

GED-MEF est un explorateur d’archive web qui organise les fichiers selon :
`RACINE / Matricule / Sous-dossier / Images`.

Le projet propose deux moteurs d’indexation :
- **PHP fallback** : scan + insertion DB 100% PHP.
- **Go scanner** : binaire Go qui lit le filesystem très rapidement et émet du JSONL pour ingestion PHP.

## 🚀 Architecture principale

```
/
├── index.php                 UI principale
├── app/
│   ├── header.php            UI et navigation
│   ├── footer.php            UI et scripts
│   ├── indexer.php           Page d’indexation PHP/Go
│   ├── scan.php              Scanner PHP optimisé
│   ├── scan_go.php           Ingestion DB depuis scanner Go
│   ├── matricules.php        API paginée
│   ├── sousdossiers.php      API des sous-dossiers
│   ├── images.php            API des images
│   └── image.php             Serveur d’images local
├── cmd/scannerfs/            Scanner Go filesystem-only
├── bin/scannerfs             Binaire Go Linux
├── bin/scannerfs.exe         Binaire Go Windows
├── scripts/
│   ├── schema.pg.sql         Schéma PostgreSQL
│   └── build_scannerfs.sh    Build multi-plateforme des binaires
└── docker-compose.yml        PostgreSQL optionnel
```

## 🌍 Support et portabilité

- **Windows** : le binaire `bin/scannerfs.exe` est utilisé pour accélérer le scan.
- **Fallback PHP** : si Go est indisponible, l’application bascule automatiquement sur `scan.php`.
- **PostgreSQL** : fonctionne en local en mode `trust`/`localhost`.
- **Portable** : le projet peut tourner dans un dossier Windows ou sur un serveur Linux.

Voir `docs/mode_emploi.md` pour la configuration avancée et le déploiement cross-plateforme.

## ⚡ Vitesse réelle du scan — PHP vs Go

Le vrai temps de scan s’affiche à la fin de chaque exécution :
- **PHP fallback** : durée mesurée par `app/scan.php` et affichée comme `Durée : Xs`.
- **Go scanner** : durée mesurée par `app/scan_go.php` et `cmd/scannerfs` via `duration_ms`.

### Ce que mesure le projet
- `scan.php` utilise `microtime(true)` pour afficher la durée en secondes.
- `scan_go.php` exécute le binaire Go, lit le flux JSONL, puis affiche la durée totale du pipeline.
- `cmd/scannerfs` génère un résumé JSON avec `duration_ms`, `matricules`, `sous_dossiers` et `images`.

### Vitesse observée
- Sur des archives volumineuses et un SSD, le scanner Go est généralement **2x à 5x plus rapide** que le scanner PHP.
- Sur un disque lent ou de petits volumes, l’écart se réduit, mais Go reste souvent plus performant.
- La vitesse dépend du matériel, du nombre de fichiers et de la latence PostgreSQL.

### Exemple de sortie Go
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

## 📦 Installation rapide

Voir `docs/USAGE.md` pour :
- configuration PostgreSQL
- lancement du serveur
- indexation avec PHP ou Go

## 📌 Bonnes pratiques

- Utilisez **SSD** pour de meilleures vitesses d’indexation.
- Exécutez plusieurs fois le même scan pour mesurer la vitesse réelle et lisser les variations de cache.
- Si `scannerfs.exe` est absent, le projet reste fonctionnel avec le moteur PHP.


# Archive Viewer — Documentation

Ce projet fournit une interface web (PHP + Tailwind) pour explorer une archive structurée en:
`RACINE / Matricule / Sous-dossier / Images`.

Deux moteurs d’indexation existent:
- **PHP (fallback)**: scan + insertion DB 100% PHP.
- **Go (FS-only)**: binaire Go robuste qui scanne le disque et renvoie du JSONL; l’ingestion en DB est faite par PHP.

## Structure (principale)

```
/
├── index.php                 UI principale
├── app/
│   ├── header.php            Header Tailwind
│   ├── footer.php            Footer
│   ├── indexer.php           Page d’indexation (choix PHP/Go)
│   ├── scan.php              Scanner PHP (fallback)
│   ├── scan_go.php           Ingestion DB depuis scanner Go
│   ├── matricules.php        API (liste paginée)
│   ├── sousdossiers.php      API (sous-dossiers d’un matricule)
│   ├── images.php            API (images d’un sous-dossier)
│   └── image.php             Sert une image depuis le disque
├── cmd/scannerfs/            Scanner Go filesystem-only (JSONL)
├── bin/scannerfs             Binaire local (Linux ici)
├── scripts/
│   ├── schema.pg.sql         Schéma PostgreSQL
│   └── build_scannerfs.sh    Build multi-plateforme du binaire
└── docker-compose.yml        PostgreSQL (mode trust) optionnel
```

## Installation rapide

Voir `docs/USAGE.md` pour:
- PostgreSQL (variables d’environnement)
- Lancer le serveur web
- Indexer via PHP ou Go

## Vitesse d’indexation (comment mesurer)

Le débit dépend énormément du disque (SSD/HDD), du nombre de fichiers, et de la latence DB.

- **PHP fallback** affiche `Durée : Xs` dans la sortie de scan (`app/scan.php`).
- **Go (FS) + ingest** affiche `Durée : Xs` dans la sortie (`app/scan_go.php`).

Pour comparer, lance le même scan 2–3 fois et garde la meilleure (cache OS).

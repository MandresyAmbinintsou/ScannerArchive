# Portable Windows (Option B)

![Windows](https://img.shields.io/badge/Windows-0078D6?logo=windows&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-336791?logo=postgresql&logoColor=white)
![Go](https://img.shields.io/badge/Go-00ADD8?logo=go&logoColor=white)

Objectif : lancer l’application sans installation complète, avec un paquet portable Windows.

## Contenu attendu

```
portable/windows/
├── run.bat
├── run_workerman.bat
├── env.example.bat
├── php/                 (ex: php-8.x-win)
│   └── php.exe
├── postgres/            (ex: postgres-16-windows)
│   ├── bin/pg_ctl.exe
│   ├── bin/initdb.exe
│   └── bin/psql.exe
├── data/                (créé automatiquement)
└── app/                 (copie du projet à la racine du zip)
    ├── index.php
    ├── app/...
    ├── scripts/schema.pg.sql
    └── bin/scannerfs.exe   (optionnel)
```

## Principe

- `run.bat` démarre le serveur PHP intégré.
- `run_workerman.bat` démarre le serveur Workerman WebSocket + HTTP.
- `bin/scannerfs.exe` accélère l’indexation en mode Go.

## Lancer

1) Copiez le projet dans `portable/windows/app/`
2) Déposez PHP portable dans `portable/windows/php/`
3) Déposez PostgreSQL portable dans `portable/windows/postgres/`
4) Optionnel : placez `scannerfs.exe` dans `portable/windows/app/bin/scannerfs.exe`

### Exécuter en mode portable

- **Sans WebSocket** : double-cliquez `portable/windows/run.bat`
- **Avec Workerman** : double-cliquez `portable/windows/run_workerman.bat`

## Accès

- Interface : `http://127.0.0.1:8000/index.php`
- Indexation : `http://127.0.0.1:8000/app/indexer.php`

## Vitesse de scan

- Le moteur Go Windows est le plus rapide sur des archives volumineuses.
- Le scan Go affiche la vraie durée dans `scan_go.php` et le binaire Go fournit `duration_ms`.
- Si Go n’est pas disponible, le système bascule automatiquement sur le scanner PHP.


# Portable Windows (Option B)

Objectif: lancer l’app depuis un zip **sans installation**, avec:
- PostgreSQL portable
- PHP portable
- le projet (ce repo)
- le scanner Go Windows `scannerfs.exe` (optionnel)

Limitations:
- il faut quand même **inclure** les binaires PostgreSQL et PHP dans le zip (ou les déposer manuellement).
- Windows a besoin du bon binaire Go (`scannerfs-windows-amd64.exe`).

## Structure attendue

```
portable/windows/
├── run.bat
├── env.example.bat
├── php/                 (ex: php-8.x-win)
│   └── php.exe
├── postgres/            (ex: postgres-16-windows)
│   ├── bin/pg_ctl.exe
│   ├── bin/initdb.exe
│   └── bin/psql.exe
├── data/                (créé automatiquement: data PG)
└── app/                 (copie du projet à la racine du zip)
    ├── index.php
    ├── app/...
    ├── scripts/schema.pg.sql
    └── bin/scannerfs.exe   (optionnel)
```

## Lancer

1) Copie le contenu du projet dans `portable/windows/app/`
2) Mets PHP portable dans `portable/windows/php/`
3) Mets PostgreSQL portable dans `portable/windows/postgres/`
4) (Optionnel) mets `scannerfs.exe` dans `portable/windows/app/bin/scannerfs.exe`
5) Double-clique `portable/windows/run.bat`

Puis ouvre:
- `http://127.0.0.1:8000/index.php`
- `http://127.0.0.1:8000/app/indexer.php`


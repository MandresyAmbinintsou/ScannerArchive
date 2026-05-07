# Archive Viewer — Documentation

![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white)
![Go](https://img.shields.io/badge/Go-00ADD8?logo=go&logoColor=white)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-336791?logo=postgresql&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-38B2AC?logo=tailwindcss&logoColor=white)

Ce projet propose une interface web moderne pour parcourir une archive organisée ainsi :
`RACINE / Matricule / Sous-dossier / Images`.

## Moteurs d’indexation

- **PHP fallback** : scan du système de fichiers + insertion directe en base avec PHP.
- **Go scanner** : binaire Go dédié qui parcourt le disque rapidement et émet du JSONL.

## Structure principale

```
/
├── index.php                 Interface principale
├── app/
│   ├── header.php            UI et navigation
│   ├── footer.php            Suite de composants
│   ├── indexer.php           Page d’indexation
│   ├── scan.php              Scanner PHP optimisé
│   ├── scan_go.php           Ingestion DB depuis Go
│   ├── matricules.php        API paginée
│   ├── sousdossiers.php      API des sous-dossiers
│   ├── images.php            API des images
│   └── image.php             Proxy image locale
├── cmd/scannerfs/            Source du scanner Go
├── bin/scannerfs             Binaire Go Linux
├── bin/scannerfs.exe         Binaire Go Windows
├── scripts/
│   ├── schema.pg.sql         Schéma PostgreSQL
│   └── build_scannerfs.sh    Génère les binaires multi-plateforme
└── docker-compose.yml        PostgreSQL optionnel
```

## Installation rapide

Voir `docs/USAGE.md` pour :
- configuration PostgreSQL
- lancement du serveur
- indexation PHP / Go

## Vitesse réelle du scan

### Mesure précise

Les deux moteurs affichent leur vraie vitesse :
- **PHP** : `app/scan.php` affiche `Durée : Xs`.
- **Go** : `scan_go.php` affiche `Durée : Xs`, et le binaire `cmd/scannerfs` fournit `duration_ms`.

### Comparaison

- Le scanner Go est optimisé pour le filesystem, le multi-coeur et le traitement parallèle.
- Le scanner PHP est robuste et compatible sur toutes les plateformes même sans binaire Go.
- Sur de gros volumes, Go est généralement **2x à 5x plus rapide** qu’un scan PHP pur.

### Comment comparer

1. Exécutez le même dossier avec les deux moteurs.
2. Comparez `Durée : Xs` dans la sortie.
3. Vérifiez le résumé JSON Go :

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

4. Sur SSD et CPU multi-coeurs, les gains sont les plus importants.


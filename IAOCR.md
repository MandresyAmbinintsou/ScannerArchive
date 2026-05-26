# Projet GED-MEF : Extension Intelligence Artificielle & OCR

Ce document définit la stratégie et les technologies nécessaires pour intégrer des capacités d'extraction automatique de données (OCR) et d'analyse intelligente (IA) au sein du projet GED-MEF.

## 1. Vision du Projet
L'objectif est de transformer l'explorateur d'archives en un système proactif capable de :
*   **Lire** les documents scannés (Images et PDF).
*   **Extraire** des données spécifiques (Ex: Matricule, montants d'avancement, dates).
*   **Structurer** ces informations dans une base de données.
*   **Afficher** un résumé intelligent des informations pour chaque matricule.

## 2. Architecture Technologique

### A. Extraction de Texte (OCR)
*   **Solution locale :** **Tesseract OCR**
    *   *Rôle :* Transforme les pixels des images en texte brut.
    *   *Installation :* Serveur Windows 8 / Linux.
*   **Solution Cloud (Alternative) :** **Google Vision API**
    *   *Rôle :* Haute précision pour les documents complexes ou de faible qualité.

### B. Analyse et Compréhension (IA)
*   **Moteur :** **Gemini Pro API** (Google)
    *   *Rôle :* Analyse le texte brut fourni par l'OCR, identifie les entités (Ex: "1000 AR") et les classifie par type (Avancement, Indemnité, etc.).
    *   *Format de sortie :* JSON structuré pour une insertion directe en base de données.

### C. Traitement des Documents
*   **Ghostscript / ImageMagick :**
    *   *Rôle :* Découper les PDF multipages en images individuelles pour l'analyse OCR.

### D. Gestion des Tâches (Background Workers)
*   **Workerman (déjà inclus) :**
    *   *Rôle :* Gérer une file d'attente de traitement. Le scan indexe les fichiers, et Workerman traite l'OCR en arrière-plan sans bloquer l'interface utilisateur.

## 3. Structure des Données (Base de Données)

Une table dédiée au stockage des informations extraites par l'IA :

```sql
CREATE TABLE ia_extractions (
    id              BIGSERIAL PRIMARY KEY,
    matricule_id    BIGINT REFERENCES matricules(id) ON DELETE CASCADE,
    image_id        BIGINT REFERENCES images(id) ON DELETE CASCADE,
    type_donnee     VARCHAR(50), -- Ex: 'avancement', 'grade', 'echelon'
    valeur_extraite TEXT,        -- Ex: '1000 AR'
    confiance       FLOAT,       -- Score de certitude de l'IA (0 à 1)
    date_document   DATE,        -- Date détectée sur le document
    analyse_le      TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);
```

## 4. Flux de Fonctionnement (Workflow)

1.  **Indexation :** Le scanner (Go/PHP) détecte un nouveau fichier dans un dossier (ex: `Avancement/2026-01-03.pdf`).
2.  **File d'attente :** Le fichier est ajouté à la file de traitement IA.
3.  **OCR :** Tesseract extrait le texte du document.
4.  **Inférence IA :** Gemini analyse le texte : *"Dans ce document, quel est l'avancement du matricule 30288 ?"*.
5.  **Stockage :** L'IA répond `{"matricule": 30288, "montant": "1000 AR"}`. Les données sont insérées dans `ia_extractions`.
6.  **Visualisation :** Sur la page d'accueil, le matricule 30288 affiche un badge : **"Dernier avancement : 1000 AR"**.

## 5. Avantages pour l'Utilisateur
*   **Gain de temps :** Plus besoin d'ouvrir chaque PDF pour chercher un montant.
*   **Recherche puissante :** Possibilité de filtrer les matricules par montant d'avancement ou par date d'effet.
*   **Mise à jour automatique :** Dès qu'un nouveau scan est déposé, les informations du matricule se mettent à jour sur le web.

6. Extraction Interactive et Apprentissage (Zonal OCR)
Pour les documents complexes ou les formulaires standardisés, une fonctionnalité de sélection manuelle sera ajoutée :
*   **Sélection Interactive :** Dans la visionneuse web, l'utilisateur peut encadrer une zone spécifique à la souris.
*   **Extraction Ciblée :** Le système recadre l'image sur cette zone pour une lecture OCR ultra-précise.
*   **Templates (Modèles) :** Possibilité d'enregistrer ces zones pour tous les documents d'un même type. L'IA appliquera alors automatiquement le même "masque" de lecture aux prochains fichiers scannés dans ce dossier.

---
*Document généré le : 26 Mai 2026*

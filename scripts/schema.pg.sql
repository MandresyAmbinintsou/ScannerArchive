-- ============================================================
-- SCHEMA PostgreSQL — Archive Viewer
-- Exécuter ce fichier en premier (exemple):
--   psql -U postgres -d archive_db -f schema.pg.sql
-- ============================================================

-- Base de données (à créer selon votre setup)
-- CREATE DATABASE archive_db;

-- Table des matricules
CREATE TABLE IF NOT EXISTS matricules (
    id              BIGSERIAL PRIMARY KEY,
    nom             VARCHAR(150) NOT NULL UNIQUE,
    chemin          TEXT NOT NULL,
    nb_sousdossiers INTEGER DEFAULT 0,
    indexe_le       TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

-- Table des sous-dossiers
CREATE TABLE IF NOT EXISTS sousdossiers (
    id              BIGSERIAL PRIMARY KEY,
    matricule_id    BIGINT NOT NULL REFERENCES matricules(id) ON DELETE CASCADE,
    nom             VARCHAR(150) NOT NULL,
    chemin          TEXT NOT NULL,
    nb_images       INTEGER DEFAULT 0
);

-- Table des images
CREATE TABLE IF NOT EXISTS images (
    id              BIGSERIAL PRIMARY KEY,
    sousdossier_id  BIGINT NOT NULL REFERENCES sousdossiers(id) ON DELETE CASCADE,
    nom_fichier     VARCHAR(255) NOT NULL,
    chemin_complet  TEXT NOT NULL
);

-- Table d'historique des dossiers scannés
CREATE TABLE IF NOT EXISTS scan_history (
    id          BIGSERIAL PRIMARY KEY,
    root_path   TEXT NOT NULL,
    root_mtime  BIGINT DEFAULT 0,
    created_at  TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

-- Table des utilisateurs
CREATE TABLE IF NOT EXISTS users (
    id              BIGSERIAL PRIMARY KEY,
    username        VARCHAR(150) NOT NULL UNIQUE,
    password_hash   TEXT NOT NULL,
    password_plain  TEXT NULL,
    role            VARCHAR(20) NOT NULL DEFAULT 'user',
    created_at      TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

-- Index performance
CREATE INDEX IF NOT EXISTS idx_mat_nom ON matricules(nom);
CREATE INDEX IF NOT EXISTS idx_sous_mat_id ON sousdossiers(matricule_id);
CREATE INDEX IF NOT EXISTS idx_img_sous_id ON images(sousdossier_id);

-- Recherche “contains” efficace sur nom (optionnel)
-- Nécessite: CREATE EXTENSION pg_trgm;
-- CREATE EXTENSION IF NOT EXISTS pg_trgm;
-- CREATE INDEX IF NOT EXISTS idx_mat_nom_trgm ON matricules USING gin (nom gin_trgm_ops);


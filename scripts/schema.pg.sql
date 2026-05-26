-- ============================================================
-- SCHEMA PostgreSQL — Archive Viewer
-- ============================================================

-- Table des matricules
CREATE TABLE matricules (
    id              BIGSERIAL PRIMARY KEY,
    nom             VARCHAR(150) NOT NULL UNIQUE,
    chemin          TEXT NOT NULL,
    nb_sousdossiers INTEGER DEFAULT 0,
    modifie_le      BIGINT DEFAULT 0,
    indexe_le       TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

-- Table des sous-dossiers
CREATE TABLE sousdossiers (
    id              BIGSERIAL PRIMARY KEY,
    matricule_id    BIGINT NOT NULL REFERENCES matricules(id) ON DELETE CASCADE,
    nom             VARCHAR(150) NOT NULL,
    chemin          TEXT NOT NULL,
    nb_images       INTEGER DEFAULT 0,
    modifie_le      BIGINT DEFAULT 0
);

-- Table des images
CREATE TABLE images (
    id              BIGSERIAL PRIMARY KEY,
    sousdossier_id  BIGINT NOT NULL REFERENCES sousdossiers(id) ON DELETE CASCADE,
    nom_fichier     VARCHAR(255) NOT NULL,
    chemin_complet  TEXT NOT NULL
);

-- Table d'historique des dossiers scannés
CREATE TABLE scan_history (
    id          BIGSERIAL PRIMARY KEY,
    root_path   TEXT NOT NULL,
    root_mtime  BIGINT DEFAULT 0,
    created_at  TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

-- Table des utilisateurs
CREATE TABLE users (
    id              BIGSERIAL PRIMARY KEY,
    username        VARCHAR(150) NOT NULL UNIQUE,
    password_hash   TEXT NOT NULL,
    password_plain  TEXT NULL,
    role            VARCHAR(20) NOT NULL DEFAULT 'user',
    created_at      TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

-- Index performance
CREATE INDEX idx_mat_nom ON matricules(nom);
CREATE INDEX idx_sous_mat_id ON sousdossiers(matricule_id);
CREATE INDEX idx_img_sous_id ON images(sousdossier_id);

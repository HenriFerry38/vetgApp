-- Création Base de données

DROP DATABASE IF EXISTS ViteEtGourmand_v2;
CREATE DATABASE ViteEtGourmand_v2
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_general_ci;
USE ViteEtGourmand_v2;

-- Les tables de références 

CREATE TABLE role (
	role_id INT AUTO_INCREMENT PRIMARY KEY,
    libelle VARCHAR(50) NOT NULL
    );

CREATE TABLE regime (
    regime_id   INT AUTO_INCREMENT PRIMARY KEY,
    libelle     VARCHAR(50) NOT NULL
);

CREATE TABLE theme (
    theme_id    INT AUTO_INCREMENT PRIMARY KEY,
    libelle     VARCHAR(50) NOT NULL
);

CREATE TABLE allergene (
    allergene_id    INT AUTO_INCREMENT PRIMARY KEY,
    libelle         VARCHAR(50) NOT NULL
);

-- Tables utilisateurs et avis

CREATE TABLE utilisateurs (
    utilisateur_id  INT AUTO_INCREMENT PRIMARY KEY,
    prenom          VARCHAR(64)  NOT NULL,
    nom             VARCHAR(64)  NOT NULL,
    telephone       VARCHAR(20)  NOT NULL,
    email           VARCHAR(254) NOT NULL UNIQUE,
    ville           VARCHAR(150) NOT NULL,
    pays            VARCHAR(50)  NOT NULL,
    adresse         VARCHAR(150) NOT NULL,
    password        VARCHAR(128) NOT NULL,
    role_id         INT NOT NULL,
    CONSTRAINT fk_utilisateurs_role
        FOREIGN KEY (role_id) REFERENCES role(role_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE TABLE avis (
    avis_id         INT AUTO_INCREMENT PRIMARY KEY,
    note            INT NOT NULL,
    description     VARCHAR(150) NOT NULL,
    statut          VARCHAR(50) NOT NULL,
    utilisateur_id  INT NOT NULL,
    CONSTRAINT fk_avis_utilisateur
        FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(utilisateur_id)
        ON DELETE CASCADE ON UPDATE CASCADE
);

-- Tables Menus et Plats

CREATE TABLE menu (
    menu_id             INT AUTO_INCREMENT PRIMARY KEY,
    titre               VARCHAR(50)  NOT NULL,
    nb_personne_mini    INT          NOT NULL,
    prix_par_personne   DECIMAL(8,2) NOT NULL,
    description         VARCHAR(150) NOT NULL,
    quantite_restaurant INT          NOT NULL,
    photo               VARCHAR(255) NULL,
    regime_id           INT NOT NULL,
    theme_id            INT NOT NULL,
    CONSTRAINT fk_menu_regime
        FOREIGN KEY (regime_id) REFERENCES regime(regime_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_menu_theme
        FOREIGN KEY (theme_id)  REFERENCES theme(theme_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE TABLE plat (
    plat_id     INT AUTO_INCREMENT PRIMARY KEY,
    titre_plat  VARCHAR(50)  NOT NULL,
    photo       VARCHAR(255) NULL
);

-- Association entre les plat et menus (un menu a plusieurs plats, un plat peut etre dans plusieurs menus)
CREATE TABLE menu_plat (
    menu_id INT NOT NULL,
    plat_id INT NOT NULL,
    PRIMARY KEY (menu_id, plat_id),
    CONSTRAINT fk_menu_plat_menu
        FOREIGN KEY (menu_id) REFERENCES menu(menu_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_menu_plat_plat
        FOREIGN KEY (plat_id) REFERENCES plat(plat_id)
        ON DELETE CASCADE ON UPDATE CASCADE
);

-- Association entre les plats et allergenes (un plat contient plusieurs allergènes, un allergène peut etre dans plusieurs plats)
CREATE TABLE plat_allergene (
    plat_id       INT NOT NULL,
    allergene_id  INT NOT NULL,
    PRIMARY KEY (plat_id, allergene_id),
    CONSTRAINT fk_plat_allergene_plat
        FOREIGN KEY (plat_id) REFERENCES plat(plat_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_plat_allergene_allergene
        FOREIGN KEY (allergene_id) REFERENCES allergene(allergene_id)
        ON DELETE CASCADE ON UPDATE CASCADE
);

-- Table des horaires
CREATE TABLE horaire (
    horaire_id      INT AUTO_INCREMENT PRIMARY KEY,
    jour            VARCHAR(50) NOT NULL,
    heure_ouverture VARCHAR(50) NOT NULL,
    heure_fermeture VARCHAR(50) NOT NULL
);

-- La table commande
CREATE TABLE commande (
    numero_commande     VARCHAR(50) PRIMARY KEY,
    date_commande       DATE        NOT NULL,
    date_prestation     DATE        NOT NULL,
    heure_prestation    VARCHAR(50) NOT NULL,
    prix_menu           DECIMAL(8,2) NOT NULL,
    nb_personne         INT         NOT NULL,
    prix_livraison      DECIMAL(8,2) NOT NULL,
    statut              VARCHAR(50) NOT NULL,
    pret_materiel       TINYINT(1)  NOT NULL,
    restitution_materiel TINYINT(1) NOT NULL,
    utilisateur_id      INT NOT NULL,
    menu_id             INT NOT NULL,
    CONSTRAINT fk_commande_utilisateur
        FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(utilisateur_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_commande_menu
        FOREIGN KEY (menu_id) REFERENCES menu(menu_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
);

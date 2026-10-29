-- Création de la base de données
CREATE DATABASE IF NOT EXISTS zouzoukou_mems
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE zouzoukou_mems;

-- Table des produits
CREATE TABLE produits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    variante VARCHAR(100) DEFAULT NULL,
    prix_unitaire DECIMAL(10, 2) NOT NULL,
    description TEXT DEFAULT NULL,
    actif BOOLEAN DEFAULT TRUE,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_modification TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_produit (nom, variante)
) ENGINE=InnoDB;

-- Insertion des produits initiaux
INSERT INTO produits (nom, variante, prix_unitaire) VALUES
('Instants Gravés', 'MINI', 1000.00),
('Instants Gravés', 'BEST SELLER', 2000.00),
('Instants Gravés', 'SLIM – 60×60 mm', 1000.00),
('Instants Gravés', 'SLIM – 65×145 mm', 1500.00);

-- Table des commandes
CREATE TABLE commandes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_nom VARCHAR(255) NOT NULL,
    client_telephone VARCHAR(20) NOT NULL,
    client_adresse TEXT DEFAULT NULL,
    canal_commande ENUM('whatsapp', 'mail', 'direct', 'autre') NOT NULL DEFAULT 'direct',
    mode_paiement VARCHAR(50) DEFAULT NULL,
    total DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    statut ENUM('en_attente', 'confirmee', 'en_preparation', 'prete', 'livree', 'annulee')
        NOT NULL DEFAULT 'en_attente',
    notes TEXT DEFAULT NULL,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_modification TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    date_livraison DATETIME DEFAULT NULL,
    INDEX idx_statut (statut),
    INDEX idx_date_creation (date_creation),
    INDEX idx_client_telephone (client_telephone)
) ENGINE=InnoDB;

-- Table des lignes de commande (détail des produits)
CREATE TABLE lignes_commandes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    commande_id INT NOT NULL,
    produit_id INT NOT NULL,
    quantite INT NOT NULL DEFAULT 1,
    prix_unitaire_historique DECIMAL(10, 2) NOT NULL,
    sous_total DECIMAL(10, 2) GENERATED ALWAYS AS (quantite * prix_unitaire_historique) STORED,
    FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE,
    FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE RESTRICT,
    INDEX idx_commande (commande_id),
    INDEX idx_produit (produit_id)
) ENGINE=InnoDB;

-- Table de suivi des changements de statut
CREATE TABLE suivi_statuts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    commande_id INT NOT NULL,
    ancien_statut VARCHAR(50) DEFAULT NULL,
    nouveau_statut VARCHAR(50) NOT NULL,
    commentaire TEXT DEFAULT NULL,
    date_changement TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE,
    INDEX idx_commande_date (commande_id, date_changement)
) ENGINE=InnoDB;

-- Trigger : historiser les changements de statut
DELIMITER //
CREATE TRIGGER after_commande_status_update
AFTER UPDATE ON commandes
FOR EACH ROW
BEGIN
    IF OLD.statut != NEW.statut THEN
        INSERT INTO suivi_statuts (commande_id, ancien_statut, nouveau_statut)
        VALUES (NEW.id, OLD.statut, NEW.statut);
    END IF;
END//
DELIMITER ;

-- Trigger : statut initial à la création
DELIMITER //
CREATE TRIGGER after_commande_insert
AFTER INSERT ON commandes
FOR EACH ROW
BEGIN
    INSERT INTO suivi_statuts (commande_id, ancien_statut, nouveau_statut)
    VALUES (NEW.id, NULL, NEW.statut);
END//
DELIMITER ;

-- Vue des commandes avec détails
CREATE VIEW vue_commandes_details AS
SELECT
    c.id as commande_id,
    c.client_nom,
    c.client_telephone,
    c.statut,
    c.canal_commande,
    c.date_creation,
    COUNT(DISTINCT lc.id) as nombre_articles,
    SUM(lc.quantite) as quantite_totale,
    c.total,
    GROUP_CONCAT(
        CONCAT(p.nom, ' - ', p.variante, ' x', lc.quantite)
        ORDER BY lc.id
        SEPARATOR ', '
    ) as articles_detail
FROM commandes c
LEFT JOIN lignes_commandes lc ON c.id = lc.commande_id
LEFT JOIN produits p ON lc.produit_id = p.id
GROUP BY c.id;

-- Vue des statistiques par produit
CREATE VIEW vue_stats_produits AS
SELECT
    p.id,
    p.nom,
    p.variante,
    p.prix_unitaire,
    COUNT(DISTINCT lc.commande_id) as nombre_commandes,
    SUM(lc.quantite) as quantite_vendue,
    SUM(lc.quantite * lc.prix_unitaire_historique) as chiffre_affaires
FROM produits p
LEFT JOIN lignes_commandes lc ON p.id = lc.produit_id
LEFT JOIN commandes c ON lc.commande_id = c.id
WHERE c.statut != 'annulee' OR c.statut IS NULL
GROUP BY p.id;

-- Vue des statistiques journalières
CREATE VIEW vue_stats_journalieres AS
SELECT
    DATE(date_creation) as jour,
    COUNT(*) as nombre_commandes,
    SUM(total) as total_ca,
    AVG(total) as panier_moyen,
    SUM(CASE WHEN statut = 'annulee' THEN 1 ELSE 0 END) as annulations
FROM commandes
GROUP BY DATE(date_creation);

-- Procédure pour créer une commande facilement
DELIMITER //
CREATE PROCEDURE creer_commande(
    IN p_client_nom VARCHAR(255),
    IN p_client_telephone VARCHAR(20),
    IN p_canal_commande VARCHAR(50),
    IN p_produits_json JSON  -- Format: [{"produit_id": 1, "quantite": 2}, ...]
)
BEGIN
    DECLARE v_commande_id INT;
    DECLARE v_total DECIMAL(10,2) DEFAULT 0;
    DECLARE v_i INT DEFAULT 0;
    DECLARE v_count INT;
    DECLARE v_produit_id INT;
    DECLARE v_quantite INT;
    DECLARE v_prix_unitaire DECIMAL(10,2);

    INSERT INTO commandes (client_nom, client_telephone, canal_commande, total)
    VALUES (p_client_nom, p_client_telephone, p_canal_commande, 0);

    SET v_commande_id = LAST_INSERT_ID();
    SET v_count = JSON_LENGTH(p_produits_json);

    WHILE v_i < v_count DO
        SET v_produit_id = JSON_UNQUOTE(JSON_EXTRACT(p_produits_json, CONCAT('$[', v_i, '].produit_id')));
        SET v_quantite   = JSON_UNQUOTE(JSON_EXTRACT(p_produits_json, CONCAT('$[', v_i, '].quantite')));

        SELECT prix_unitaire INTO v_prix_unitaire
        FROM produits
        WHERE id = v_produit_id AND actif = TRUE;

        INSERT INTO lignes_commandes (commande_id, produit_id, quantite, prix_unitaire_historique)
        VALUES (v_commande_id, v_produit_id, v_quantite, v_prix_unitaire);

        SET v_total = v_total + (v_prix_unitaire * v_quantite);
        SET v_i = v_i + 1;
    END WHILE;

    UPDATE commandes SET total = v_total WHERE id = v_commande_id;

    SELECT v_commande_id as commande_id, v_total as total;
END//
DELIMITER ;

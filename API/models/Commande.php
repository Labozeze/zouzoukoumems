<?php
class Commande {
    private $conn;
    private $table = "commandes";

    public function __construct($db) {
        $this->conn = $db;
    }

    // Créer une commande
    public function create($data) {
        try {
            $this->conn->beginTransaction();

            // Insérer la commande
            $query = "INSERT INTO " . $this->table . "
                      (client_nom, client_telephone, client_adresse, canal_commande,
                       mode_paiement, total, statut, notes)
                      VALUES
                      (:client_nom, :client_telephone, :client_adresse, :canal_commande,
                       :mode_paiement, :total, :statut, :notes)";

            $stmt = $this->conn->prepare($query);

            $total = 0;
            foreach ($data['articles'] as $article) {
                $total += $article['prix_unitaire'] * $article['quantite'];
            }

            $stmt->execute([
                ':client_nom'       => $data['client_nom'],
                ':client_telephone' => $data['client_telephone'],
                ':client_adresse'   => $data['client_adresse'] ?? null,
                ':canal_commande'   => $data['canal_commande'] ?? 'direct',
                ':mode_paiement'    => $data['mode_paiement'] ?? null,
                ':total'            => $total,
                ':statut'           => $data['statut'] ?? 'en_attente',
                ':notes'            => $data['notes'] ?? null
            ]);

            $commande_id = $this->conn->lastInsertId();

            // Insérer les lignes de commande
            $queryLigne = "INSERT INTO lignes_commandes
                           (commande_id, produit_id, quantite, prix_unitaire_historique)
                           VALUES (:commande_id, :produit_id, :quantite, :prix_unitaire)";

            $stmtLigne = $this->conn->prepare($queryLigne);

            foreach ($data['articles'] as $article) {
                $stmtLigne->execute([
                    ':commande_id'   => $commande_id,
                    ':produit_id'    => $article['produit_id'],
                    ':quantite'      => $article['quantite'],
                    ':prix_unitaire' => $article['prix_unitaire']
                ]);
            }

            $this->conn->commit();

            return [
                'success'     => true,
                'message'     => 'Commande créée avec succès',
                'commande_id' => $commande_id,
                'total'       => $total
            ];

        } catch (Exception $e) {
            $this->conn->rollBack();
            throw new Exception("Erreur lors de la création : " . $e->getMessage());
        }
    }

    // Rechercher des commandes
    public function search($params = []) {
        $query = "SELECT c.*,
                         COUNT(lc.id) as nombre_articles,
                         GROUP_CONCAT(
                             CONCAT(p.nom, ' - ', p.variante, ' x', lc.quantite)
                             ORDER BY lc.id
                             SEPARATOR ', '
                         ) as articles_detail
                  FROM " . $this->table . " c
                  LEFT JOIN lignes_commandes lc ON c.id = lc.commande_id
                  LEFT JOIN produits p ON lc.produit_id = p.id
                  WHERE 1=1";

        $bindings = [];

        // Recherche par ID
        if (!empty($params['id'])) {
            $query .= " AND c.id = :id";
            $bindings[':id'] = $params['id'];
        }

        // Recherche par téléphone
        if (!empty($params['telephone'])) {
            $query .= " AND c.client_telephone LIKE :telephone";
            $bindings[':telephone'] = '%' . $params['telephone'] . '%';
        }

        // Recherche par nom
        if (!empty($params['nom'])) {
            $query .= " AND c.client_nom LIKE :nom";
            $bindings[':nom'] = '%' . $params['nom'] . '%';
        }

        // Filtrer par statut
        if (!empty($params['statut'])) {
            $query .= " AND c.statut = :statut";
            $bindings[':statut'] = $params['statut'];
        }

        // Filtrer par date
        if (!empty($params['date_debut'])) {
            $query .= " AND DATE(c.date_creation) >= :date_debut";
            $bindings[':date_debut'] = $params['date_debut'];
        }

        if (!empty($params['date_fin'])) {
            $query .= " AND DATE(c.date_creation) <= :date_fin";
            $bindings[':date_fin'] = $params['date_fin'];
        }

        // Filtrer par canal
        if (!empty($params['canal'])) {
            $query .= " AND c.canal_commande = :canal";
            $bindings[':canal'] = $params['canal'];
        }

        $query .= " GROUP BY c.id ORDER BY c.date_creation DESC";

        // Pagination
        $page   = $params['page'] ?? 1;
        $limit  = $params['limit'] ?? 10;
        $offset = ($page - 1) * $limit;

        $query .= " LIMIT :limit OFFSET :offset";
        $bindings[':limit']  = (int)$limit;
        $bindings[':offset'] = (int)$offset;

        $stmt = $this->conn->prepare($query);
        $stmt->execute($bindings);

        $commandes = $stmt->fetchAll();

        // Compter le total pour la pagination
        $countQuery = "SELECT COUNT(DISTINCT c.id) as total
                       FROM " . $this->table . " c
                       WHERE 1=1";

        // Reproduire les mêmes filtres pour le count
        $countBindings = [];
        if (!empty($params['statut'])) {
            $countQuery .= " AND c.statut = :statut";
            $countBindings[':statut'] = $params['statut'];
        }
        if (!empty($params['date_debut'])) {
            $countQuery .= " AND DATE(c.date_creation) >= :date_debut";
            $countBindings[':date_debut'] = $params['date_debut'];
        }
        if (!empty($params['date_fin'])) {
            $countQuery .= " AND DATE(c.date_creation) <= :date_fin";
            $countBindings[':date_fin'] = $params['date_fin'];
        }

        $countStmt = $this->conn->prepare($countQuery);
        $countStmt->execute($countBindings);
        $total = $countStmt->fetch()['total'];

        return [
            'success' => true,
            'data' => $commandes,
            'pagination' => [
                'page'  => (int)$page,
                'limit' => (int)$limit,
                'total' => (int)$total,
                'pages' => ceil($total / $limit)
            ]
        ];
    }

    // Récupérer une commande par ID avec détails
    public function getById($id) {
        // Commande
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':id' => $id]);
        $commande = $stmt->fetch();

        if (!$commande) {
            return null;
        }

        // Lignes de commande
        $queryLignes = "SELECT lc.*, p.nom, p.variante
                        FROM lignes_commandes lc
                        JOIN produits p ON lc.produit_id = p.id
                        WHERE lc.commande_id = :id";
        $stmtLignes = $this->conn->prepare($queryLignes);
        $stmtLignes->execute([':id' => $id]);
        $commande['articles'] = $stmtLignes->fetchAll();

        // Historique des statuts
        $querySuivi = "SELECT * FROM suivi_statuts
                       WHERE commande_id = :id
                       ORDER BY date_changement";
        $stmtSuivi = $this->conn->prepare($querySuivi);
        $stmtSuivi->execute([':id' => $id]);
        $commande['suivi_statuts'] = $stmtSuivi->fetchAll();

        return $commande;
    }

    // Mettre à jour une commande
    public function update($id, $data) {
        try {
            $this->conn->beginTransaction();

            $updates  = [];
            $bindings = [':id' => $id];

            // Champs modifiables
            $allowedFields = [
                'client_nom', 'client_telephone', 'client_adresse',
                'canal_commande', 'mode_paiement', 'notes'
            ];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = :$field";
                    $bindings[":$field"] = $data[$field];
                }
            }

            if (!empty($updates)) {
                $query = "UPDATE " . $this->table . " SET " .
                         implode(', ', $updates) .
                         " WHERE id = :id";

                $stmt = $this->conn->prepare($query);
                $stmt->execute($bindings);
            }

            // Mettre à jour les articles si fournis
            if (isset($data['articles'])) {
                // Supprimer les anciennes lignes
                $stmtDelete = $this->conn->prepare(
                    "DELETE FROM lignes_commandes WHERE commande_id = :id"
                );
                $stmtDelete->execute([':id' => $id]);

                // Réinsérer les nouvelles lignes
                $queryInsert = "INSERT INTO lignes_commandes
                                (commande_id, produit_id, quantite, prix_unitaire_historique)
                                VALUES (:commande_id, :produit_id, :quantite, :prix_unitaire)";
                $stmtInsert = $this->conn->prepare($queryInsert);

                $total = 0;
                foreach ($data['articles'] as $article) {
                    $stmtInsert->execute([
                        ':commande_id'   => $id,
                        ':produit_id'    => $article['produit_id'],
                        ':quantite'      => $article['quantite'],
                        ':prix_unitaire' => $article['prix_unitaire']
                    ]);
                    $total += $article['prix_unitaire'] * $article['quantite'];
                }

                // Mettre à jour le total
                $stmtTotal = $this->conn->prepare(
                    "UPDATE commandes SET total = :total WHERE id = :id"
                );
                $stmtTotal->execute([':total' => $total, ':id' => $id]);
            }

            $this->conn->commit();

            return [
                'success' => true,
                'message' => 'Commande mise à jour avec succès'
            ];

        } catch (Exception $e) {
            $this->conn->rollBack();
            throw new Exception("Erreur lors de la mise à jour : " . $e->getMessage());
        }
    }

    // Mettre à jour le statut
    public function updateStatus($id, $nouveau_statut) {
        $query = "UPDATE " . $this->table .
                 " SET statut = :statut, date_livraison = :date_livraison
                   WHERE id = :id";

        $params = [
            ':id'             => $id,
            ':statut'         => $nouveau_statut,
            ':date_livraison' => $nouveau_statut === 'livree' ? date('Y-m-d H:i:s') : null
        ];

        $stmt = $this->conn->prepare($query);

        if ($stmt->execute($params)) {
            return [
                'success' => true,
                'message' => "Statut mis à jour : $nouveau_statut"
            ];
        }

        return [
            'success' => false,
            'message' => 'Erreur lors de la mise à jour du statut'
        ];
    }

    // Récupérer les statistiques (version finale : + top canaux, taux, détails journaliers)
    public function getStats($periode = 'today') {
        switch ($periode) {
            case 'today':
                $date = date('Y-m-d');
                break;
            case 'week':
                $date = date('Y-m-d', strtotime('-7 days'));
                break;
            case 'month':
                $date = date('Y-m-d', strtotime('-30 days'));
                break;
            case 'year':
                $date = date('Y-m-d', strtotime('-365 days'));
                break;
            default:
                $date = date('Y-m-d');
        }

        // Statistiques générales
        $query = "SELECT
                    COUNT(*) as total_commandes,
                    SUM(CASE WHEN statut != 'annulee' THEN total ELSE 0 END) as chiffre_affaires,
                    AVG(CASE WHEN statut != 'annulee' THEN total ELSE NULL END) as panier_moyen,
                    SUM(CASE WHEN statut = 'annulee' THEN 1 ELSE 0 END) as annulations,
                    SUM(CASE WHEN statut = 'en_attente' THEN 1 ELSE 0 END) as en_attente,
                    SUM(CASE WHEN statut = 'confirmee' THEN 1 ELSE 0 END) as confirmees,
                    SUM(CASE WHEN statut = 'en_preparation' THEN 1 ELSE 0 END) as en_preparation,
                    SUM(CASE WHEN statut = 'prete' THEN 1 ELSE 0 END) as pretes,
                    SUM(CASE WHEN statut = 'livree' THEN 1 ELSE 0 END) as livrees
                  FROM commandes
                  WHERE date_creation >= :date";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([':date' => $date]);
        $stats = $stmt->fetch();

        // Taux de conversion / annulation
        if ($stats['total_commandes'] > 0) {
            $stats['taux_annulation'] = round(($stats['annulations'] / $stats['total_commandes']) * 100, 2);
            $stats['taux_conversion'] = round(($stats['livrees'] / $stats['total_commandes']) * 100, 2);
        } else {
            $stats['taux_annulation'] = 0;
            $stats['taux_conversion'] = 0;
        }

        // Top produits
        $queryProduits = "SELECT
                            p.nom,
                            p.variante,
                            SUM(lc.quantite) as total_vendus,
                            COUNT(DISTINCT lc.commande_id) as nombre_commandes,
                            SUM(lc.quantite * lc.prix_unitaire_historique) as chiffre_affaires
                          FROM lignes_commandes lc
                          JOIN produits p ON lc.produit_id = p.id
                          JOIN commandes c ON lc.commande_id = c.id
                          WHERE c.date_creation >= :date
                            AND c.statut != 'annulee'
                          GROUP BY lc.produit_id
                          ORDER BY total_vendus DESC
                          LIMIT 10";

        $stmtProduits = $this->conn->prepare($queryProduits);
        $stmtProduits->execute([':date' => $date]);
        $stats['top_produits'] = $stmtProduits->fetchAll();

        // Top canaux de vente
        $queryCanaux = "SELECT
                          canal_commande,
                          COUNT(*) as nombre_commandes,
                          SUM(CASE WHEN statut != 'annulee' THEN total ELSE 0 END) as chiffre_affaires,
                          AVG(CASE WHEN statut != 'annulee' THEN total ELSE NULL END) as panier_moyen,
                          SUM(CASE WHEN statut = 'livree' THEN 1 ELSE 0 END) as commandes_livrees,
                          SUM(CASE WHEN statut = 'annulee' THEN 1 ELSE 0 END) as commandes_annulees,
                          ROUND(
                              SUM(CASE WHEN statut != 'annulee' THEN total ELSE 0 END) * 100.0 /
                              (SELECT SUM(total) FROM commandes WHERE date_creation >= :date1 AND statut != 'annulee'),
                              2
                          ) as pourcentage_ca,
                          ROUND(
                              COUNT(*) * 100.0 /
                              (SELECT COUNT(*) FROM commandes WHERE date_creation >= :date2),
                              2
                          ) as pourcentage_commandes
                        FROM commandes
                        WHERE date_creation >= :date3
                        GROUP BY canal_commande
                        ORDER BY chiffre_affaires DESC";

        $stmtCanaux = $this->conn->prepare($queryCanaux);
        $stmtCanaux->execute([
            ':date1' => $date,
            ':date2' => $date,
            ':date3' => $date
        ]);
        $stats['top_canaux'] = $stmtCanaux->fetchAll();

        // Détail par jour pour les graphiques (période "week" uniquement)
        if ($periode === 'week') {
            $queryJournalier = "SELECT
                                  DATE(date_creation) as jour,
                                  canal_commande,
                                  COUNT(*) as nombre_commandes,
                                  SUM(CASE WHEN statut != 'annulee' THEN total ELSE 0 END) as chiffre_affaires
                                FROM commandes
                                WHERE date_creation >= :date
                                GROUP BY DATE(date_creation), canal_commande
                                ORDER BY jour DESC, chiffre_affaires DESC";

            $stmtJournalier = $this->conn->prepare($queryJournalier);
            $stmtJournalier->execute([':date' => $date]);
            $stats['details_journaliers'] = $stmtJournalier->fetchAll();
        }

        // Performance des canaux par statut
        $queryCanauxStatuts = "SELECT
                                 canal_commande,
                                 statut,
                                 COUNT(*) as nombre
                               FROM commandes
                               WHERE date_creation >= :date
                               GROUP BY canal_commande, statut
                               ORDER BY canal_commande,
                                        FIELD(statut, 'en_attente', 'confirmee', 'en_preparation', 'prete', 'livree', 'annulee')";

        $stmtCanauxStatuts = $this->conn->prepare($queryCanauxStatuts);
        $stmtCanauxStatuts->execute([':date' => $date]);
        $stats['canaux_par_statut'] = $stmtCanauxStatuts->fetchAll();

        return $stats;
    }
}

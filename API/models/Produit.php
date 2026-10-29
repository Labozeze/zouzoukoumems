<?php
class Produit {
    private $conn;
    private $table = "produits";

    public function __construct($db) {
        $this->conn = $db;
    }

    // Récupérer tous les produits
    public function getAll($actifOnly = true) {
        $query = "SELECT * FROM " . $this->table;

        if ($actifOnly) {
            $query .= " WHERE actif = TRUE";
        }

        $query .= " ORDER BY nom ASC, variante ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    // Récupérer un produit par ID
    public function getById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':id' => $id]);

        return $stmt->fetch();
    }

    // Rechercher des produits
    public function search($params = []) {
        $query = "SELECT * FROM " . $this->table . " WHERE 1=1";
        $bindings = [];

        if (!empty($params['nom'])) {
            $query .= " AND nom LIKE :nom";
            $bindings[':nom'] = '%' . $params['nom'] . '%';
        }

        if (!empty($params['variante'])) {
            $query .= " AND variante LIKE :variante";
            $bindings[':variante'] = '%' . $params['variante'] . '%';
        }

        if (isset($params['actif'])) {
            $query .= " AND actif = :actif";
            $bindings[':actif'] = $params['actif'] ? 1 : 0;
        }

        if (!empty($params['prix_min'])) {
            $query .= " AND prix_unitaire >= :prix_min";
            $bindings[':prix_min'] = $params['prix_min'];
        }

        if (!empty($params['prix_max'])) {
            $query .= " AND prix_unitaire <= :prix_max";
            $bindings[':prix_max'] = $params['prix_max'];
        }

        $query .= " ORDER BY nom ASC, prix_unitaire ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute($bindings);

        return $stmt->fetchAll();
    }

    // Créer un produit
    public function create($data) {
        $query = "INSERT INTO " . $this->table . "
                  (nom, variante, prix_unitaire, description, actif)
                  VALUES (:nom, :variante, :prix_unitaire, :description, :actif)";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':nom'           => $data['nom'],
            ':variante'      => $data['variante'] ?? null,
            ':prix_unitaire' => $data['prix_unitaire'],
            ':description'   => $data['description'] ?? null,
            ':actif'         => $data['actif'] ?? true
        ]);

        return $this->conn->lastInsertId();
    }

    // Mettre à jour un produit
    public function update($id, $data) {
        $updates  = [];
        $bindings = [':id' => $id];

        $allowedFields = ['nom', 'variante', 'prix_unitaire', 'description', 'actif'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = :$field";
                $bindings[":$field"] = $data[$field];
            }
        }

        if (empty($updates)) {
            return false;
        }

        $query = "UPDATE " . $this->table . " SET " .
                 implode(', ', $updates) .
                 " WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        return $stmt->execute($bindings);
    }

    // Désactiver un produit (suppression logique)
    public function delete($id) {
        $query = "UPDATE " . $this->table . " SET actif = FALSE WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([':id' => $id]);
    }

    // Activer un produit
    public function activate($id) {
        $query = "UPDATE " . $this->table . " SET actif = TRUE WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([':id' => $id]);
    }

    // Vérifier si un produit existe
    public function exists($id) {
        $query = "SELECT COUNT(*) as count FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch()['count'] > 0;
    }
}

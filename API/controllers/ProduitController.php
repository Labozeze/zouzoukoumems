<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Produit.php';

class ProduitController {
    private $produit;

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();
        $this->produit = new Produit($db);
    }

    // Traiter la requête
    public function handleRequest() {
        // Headers CORS
        header('Content-Type: application/json; charset=UTF-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }

        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri = str_replace('/API/', '', $uri);
        $uriSegments = explode('/', trim($uri, '/'));

        try {
            switch ($method) {
                case 'GET':
                    if ($uri === 'produits') {
                        $this->getAll();
                    } elseif (isset($uriSegments[1]) && $uriSegments[0] === 'produits' && $uriSegments[1] === 'search') {
                        $this->search();
                    } elseif (isset($uriSegments[1]) && $uriSegments[0] === 'produits') {
                        $this->getById($uriSegments[1]);
                    }
                    break;

                case 'POST':
                    if ($uri === 'produits') {
                        $this->create();
                    }
                    break;

                case 'PUT':
                    if (isset($uriSegments[1]) && $uriSegments[0] === 'produits') {
                        if (isset($uriSegments[2]) && $uriSegments[2] === 'activate') {
                            $this->activate($uriSegments[1]);
                        } else {
                            $this->update($uriSegments[1]);
                        }
                    }
                    break;

                case 'DELETE':
                    if (isset($uriSegments[1]) && $uriSegments[0] === 'produits') {
                        $this->delete($uriSegments[1]);
                    }
                    break;

                default:
                    throw new Exception('Méthode non supportée');
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    // GET /produits - Liste de tous les produits
    private function getAll() {
        $actifOnly = $_GET['actif'] ?? 'true';
        $actifOnly = $actifOnly === 'true' || $actifOnly === '1';

        $produits = $this->produit->getAll($actifOnly);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $produits,
            'total' => count($produits)
        ]);
    }

    // GET /produits/{id} - Détail d'un produit
    private function getById($id) {
        $produit = $this->produit->getById($id);

        if ($produit) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => $produit
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Produit non trouvé'
            ]);
        }
    }

    // GET /produits/search - Rechercher des produits
    private function search() {
        $params = $_GET;
        $produits = $this->produit->search($params);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $produits,
            'total' => count($produits)
        ]);
    }

    // POST /produits - Créer un produit
    private function create() {
        $data = json_decode(file_get_contents('php://input'), true);

        // Validation
        if (empty($data['nom']) || !isset($data['prix_unitaire'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Le nom et le prix unitaire sont requis'
            ]);
            return;
        }

        if ($data['prix_unitaire'] < 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Le prix ne peut pas être négatif'
            ]);
            return;
        }

        try {
            $id = $this->produit->create($data);
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Produit créé avec succès',
                'produit_id' => $id
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de la création : ' . $e->getMessage()
            ]);
        }
    }

    // PUT /produits/{id} - Modifier un produit
    private function update($id) {
        if (!$this->produit->exists($id)) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Produit non trouvé'
            ]);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Aucune donnée à mettre à jour'
            ]);
            return;
        }

        if (isset($data['prix_unitaire']) && $data['prix_unitaire'] < 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Le prix ne peut pas être négatif'
            ]);
            return;
        }

        try {
            $this->produit->update($id, $data);
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Produit mis à jour avec succès'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour : ' . $e->getMessage()
            ]);
        }
    }

    // DELETE /produits/{id} - Désactiver un produit
    private function delete($id) {
        if (!$this->produit->exists($id)) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Produit non trouvé'
            ]);
            return;
        }

        try {
            $this->produit->delete($id);
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Produit désactivé avec succès'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de la désactivation : ' . $e->getMessage()
            ]);
        }
    }

    // PUT /produits/{id}/activate - Activer un produit
    private function activate($id) {
        if (!$this->produit->exists($id)) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Produit non trouvé'
            ]);
            return;
        }

        try {
            $this->produit->activate($id);
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Produit activé avec succès'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de l\'activation : ' . $e->getMessage()
            ]);
        }
    }
}

<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Commande.php';

class CommandeController {
    private $commande;

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();
        $this->commande = new Commande($db);
    }

    // Traiter la requête
    public function handleRequest() {
        // Headers CORS
        header('Content-Type: application/json; charset=UTF-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }

        // Récupérer la méthode HTTP et l'URI
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri = str_replace('/API/', '', $uri);
        $uriSegments = explode('/', trim($uri, '/'));

        try {
            switch ($method) {
                case 'POST':
                    if ($uri === 'commandes') {
                        $this->createCommande();
                    } elseif (isset($uriSegments[1]) && $uriSegments[0] === 'commandes' && $uriSegments[1] === 'search') {
                        $this->searchCommandes();
                    }
                    break;

                case 'GET':
                    if (isset($uriSegments[1]) && $uriSegments[0] === 'commandes') {
                        if ($uriSegments[1] === 'stats') {
                            $this->getStats();
                        } else {
                            $this->getCommande($uriSegments[1]);
                        }
                    } elseif ($uriSegments[0] === 'commandes') {
                        $this->searchCommandes();
                    }
                    break;

                case 'PUT':
                    if (isset($uriSegments[1]) && $uriSegments[0] === 'commandes') {
                        if (isset($uriSegments[2]) && $uriSegments[2] === 'statut') {
                            $this->updateStatus($uriSegments[1]);
                        } else {
                            $this->updateCommande($uriSegments[1]);
                        }
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

    private function createCommande() {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['client_nom']) || empty($data['client_telephone']) || empty($data['articles'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Données manquantes : client_nom, client_telephone, articles'
            ]);
            return;
        }

        // Une commande passée depuis le site démarre toujours "en attente"
        if (!IS_ADMIN) {
            unset($data['statut']);
        }

        $result = $this->commande->create($data);
        http_response_code(201);
        echo json_encode($result);
    }

    private function getCommande($id) {
        $commande = $this->commande->getById($id);

        if ($commande) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => $commande
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Commande non trouvée'
            ]);
        }
    }

    private function searchCommandes() {
        $params = [];

        // GET ou POST
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $params = $_GET;
        } else {
            $params = json_decode(file_get_contents('php://input'), true) ?? [];
        }

        $result = $this->commande->search($params);
        http_response_code(200);
        echo json_encode($result);
    }

    private function updateCommande($id) {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Aucune donnée à mettre à jour'
            ]);
            return;
        }

        $result = $this->commande->update($id, $data);
        http_response_code(200);
        echo json_encode($result);
    }

    private function updateStatus($id) {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['statut'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Statut requis'
            ]);
            return;
        }

        $result = $this->commande->updateStatus($id, $data['statut']);
        http_response_code(200);
        echo json_encode($result);
    }

    private function getStats() {
        $periode = $_GET['periode'] ?? 'today';
        $stats = $this->commande->getStats($periode);
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $stats
        ]);
    }
}

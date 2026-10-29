<?php
require_once __DIR__ . '/controllers/CommandeController.php';
require_once __DIR__ . '/controllers/ProduitController.php';

// Gestion des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Vérification de la clé API (header HTTP : X-API-Key)
$api_key = $_SERVER['HTTP_X_API_KEY'] ?? '';

if ($api_key !== '0123456789') {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Non autorisé - Clé API invalide'
    ]);
    exit();
}

// Router simple
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = str_replace('/API/', '', $uri);
$resource = explode('/', trim($uri, '/'))[0];

try {
    switch ($resource) {
        case 'commandes':
        case 'commandes/stats':
            $controller = new CommandeController();
            break;

        case 'produits':
            $controller = new ProduitController();
            break;

        default:
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Route non trouvée'
            ]);
            exit();
    }

    $controller->handleRequest();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur : ' . $e->getMessage()
    ]);
}

<?php
require_once __DIR__ . '/controllers/CommandeController.php';
require_once __DIR__ . '/controllers/ProduitController.php';

// Gestion des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=UTF-8');

// Router simple
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = trim(str_replace('/API/', '', $uri), '/');
$resource = explode('/', $uri)[0];
$method = $_SERVER['REQUEST_METHOD'];

// Vérification de la clé API (header HTTP : X-API-Key)
// - clé publique (utilisée par le site) : lister les produits et passer commande
// - clé admin (secrète, dans config/db.local.php) : toutes les routes
$config_file = __DIR__ . '/config/db.local.php';
$config = file_exists($config_file) ? require $config_file : [];
$admin_key = $config['admin_key'] ?? '';
$api_key = $_SERVER['HTTP_X_API_KEY'] ?? '';

$is_admin = $admin_key !== '' && hash_equals($admin_key, $api_key);
$is_public = hash_equals('0123456789', $api_key);
$is_public_route = ($method === 'GET' && $resource === 'produits')
                || ($method === 'POST' && $uri === 'commandes');

define('IS_ADMIN', $is_admin);

if (!$is_admin && !($is_public && $is_public_route)) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Non autorisé - Clé API invalide'
    ]);
    exit();
}

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

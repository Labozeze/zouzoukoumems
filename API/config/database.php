<?php
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
        // Identifiants dans db.local.php (hors Git, à créer sur le serveur
        // à partir de db.local.example.php)
        $config_file = __DIR__ . '/db.local.php';
        if (!file_exists($config_file)) {
            throw new Exception("Configuration de la base de données manquante");
        }
        $config = require $config_file;

        $this->host = $config['host'];
        $this->db_name = $config['db_name'];
        $this->username = $config['username'];
        $this->password = $config['password'];
    }

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            throw new Exception("Erreur de connexion : " . $e->getMessage());
        }
        return $this->conn;
    }
}

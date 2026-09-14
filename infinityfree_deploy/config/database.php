<?php
// config/database.php - InfinityFree & Localhost Database Connection

class Database {
    // InfinityFree credentials as detected from your phpMyAdmin
    private $host = "sql301.infinityfree.com";
    private $db_name = "if0_42910496_mycost_db";
    private $username = "if0_42910496";
    private $password = ""; // Your InfinityFree vPanel password
    private $conn = null;

    public function __construct() {
        // Fallback to localhost if running locally
        if (in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1', '::1'])) {
            $this->host = "localhost";
            $this->db_name = "mycost_db";
            $this->username = "root";
            $this->password = "";
        }
    }

    public function getConnection() {
        if ($this->conn !== null) {
            return $this->conn;
        }

        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $this->conn = new PDO($dsn, $this->username, $this->password, $options);

        } catch (PDOException $e) {
            $this->conn = null;
        }

        return $this->conn;
    }
}

// Utility JSON response helpers
function sendJsonResponse($data, $statusCode = 200) {
    if (!headers_sent()) {
        http_response_code($statusCode);
        header("Content-Type: application/json; charset=UTF-8");
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if (!headers_sent()) {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
        http_response_code(200);
    }
    exit;
}

<?php
// api/auth.php - Authentication API (Register, Login, Me, Logout)
require_once __DIR__ . '/../config/database.php';

session_start();

$db = new Database();
$conn = $db->getConnection();

if (!$conn) {
    sendJsonResponse(['status' => 'error', 'message' => 'Gagal terhubung ke database MySQL.'], 500);
}

$action = isset($_GET['action']) ? $_GET['action'] : '';
$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true) ?: $_POST;

// Helper to get active user ID from session or header
function getAuthUserId() {
    if (!empty($_SESSION['user_id'])) {
        return (int)$_SESSION['user_id'];
    }
    // Also support Bearer or User ID in header
    $headers = getallheaders();
    if (!empty($headers['X-User-Id'])) {
        return (int)$headers['X-User-Id'];
    }
    if (!empty($headers['x-user-id'])) {
        return (int)$headers['x-user-id'];
    }
    return 1; // Default fallback user
}

switch ($action) {
    case 'register':
        handleRegister($conn, $data);
        break;
    case 'login':
        handleLogin($conn, $data);
        break;
    case 'me':
        handleMe($conn);
        break;
    case 'logout':
        handleLogout();
        break;
    default:
        sendJsonResponse(['status' => 'error', 'message' => 'Aksi autentikasi tidak valid.'], 400);
}

function handleRegister($conn, $data) {
    if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
        sendJsonResponse(['status' => 'error', 'message' => 'Nama, Email, dan Password wajib diisi.'], 400);
    }

    $name = trim($data['name']);
    $email = strtolower(trim($data['email']));
    $password = $data['password'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendJsonResponse(['status' => 'error', 'message' => 'Format email tidak valid.'], 400);
    }

    if (strlen($password) < 6) {
        sendJsonResponse(['status' => 'error', 'message' => 'Password minimal 6 karakter.'], 400);
    }

    // Check existing email
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        sendJsonResponse(['status' => 'error', 'message' => 'Email sudah terdaftar. Silakan login.'], 409);
    }

    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    $insert = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
    $insert->execute([$name, $email, $hashedPassword]);
    $userId = (int)$conn->lastInsertId();

    $_SESSION['user_id'] = $userId;
    $_SESSION['user_name'] = $name;
    $_SESSION['user_email'] = $email;

    sendJsonResponse([
        'status' => 'success',
        'message' => 'Pendaftaran akun berhasil!',
        'user' => [
            'id' => $userId,
            'name' => $name,
            'email' => $email
        ]
    ], 201);
}

function handleLogin($conn, $data) {
    if (empty($data['email']) || empty($data['password'])) {
        sendJsonResponse(['status' => 'error', 'message' => 'Email dan Password wajib diisi.'], 400);
    }

    $email = strtolower(trim($data['email']));
    $password = $data['password'];

    $stmt = $conn->prepare("SELECT id, name, email, password FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        sendJsonResponse(['status' => 'error', 'message' => 'Email atau Password salah.'], 401);
    }

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];

    sendJsonResponse([
        'status' => 'success',
        'message' => 'Login berhasil!',
        'user' => [
            'id' => (int)$user['id'],
            'name' => $user['name'],
            'email' => $user['email']
        ]
    ]);
}

function handleMe($conn) {
    $userId = getAuthUserId();
    $stmt = $conn->prepare("SELECT id, name, email, created_at FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if ($user) {
        sendJsonResponse([
            'status' => 'success',
            'authenticated' => true,
            'user' => [
                'id' => (int)$user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'created_at' => $user['created_at']
            ]
        ]);
    } else {
        sendJsonResponse([
            'status' => 'success',
            'authenticated' => false,
            'user' => null
        ]);
    }
}

function handleLogout() {
    session_destroy();
    sendJsonResponse([
        'status' => 'success',
        'message' => 'Logout berhasil.'
    ]);
}
